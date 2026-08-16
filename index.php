<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

/* =========================================================
   AJAX endpoints — index.php?action=fetch | post
   Everything below exits before the HTML is reached.
   ========================================================= */

$action = $_GET['action'] ?? null;

if ($action !== null) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');

    try {
        switch ($action) {
            case 'fetch':
                echo json_encode(action_fetch());
                break;
            case 'post':
                echo json_encode(action_post());
                break;
            default:
                http_response_code(404);
                echo json_encode(['error' => 'Unknown action.']);
        }
    } catch (Throwable $e) {
        error_log('board: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'The board is having trouble. Try again in a moment.']);
    }

    exit;
}

/**
 * Return messages newer than ?since=<id>.
 * since=0 returns the tail of the history.
 */
function action_fetch(): array
{
    $since = (int) ($_GET['since'] ?? 0);

    if ($since > 0) {
        $stmt = db()->prepare(
            'SELECT id, handle, message, UNIX_TIMESTAMP(created_at) AS ts
               FROM messages
              WHERE id > :since
           ORDER BY id ASC
              LIMIT :lim'
        );
        $stmt->bindValue(':since', $since, PDO::PARAM_INT);
        $stmt->bindValue(':lim', HISTORY_LIMIT, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } else {
        // Newest N, then flip so the oldest is first.
        $stmt = db()->prepare(
            'SELECT id, handle, message, UNIX_TIMESTAMP(created_at) AS ts
               FROM messages
           ORDER BY id DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':lim', HISTORY_LIMIT, PDO::PARAM_INT);
        $stmt->execute();
        $rows = array_reverse($stmt->fetchAll());
    }

    foreach ($rows as &$row) {
        $row['id'] = (int) $row['id'];
        $row['ts'] = (int) $row['ts'];
    }

    return ['messages' => $rows];
}

/**
 * Insert one message. Expects POST handle + message.
 */
function action_post(): array
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        return ['error' => 'Use POST to send a message.'];
    }

    $handle  = clean_text((string) ($_POST['handle'] ?? ''));
    $message = clean_text((string) ($_POST['message'] ?? ''));

    if ($handle === '') {
        http_response_code(422);
        return ['error' => 'Pick a handle before posting.'];
    }
    if ($message === '') {
        http_response_code(422);
        return ['error' => 'Write something first.'];
    }
    if (mb_strlen($handle) > MAX_HANDLE_LENGTH) {
        http_response_code(422);
        return ['error' => 'Handles cap at ' . MAX_HANDLE_LENGTH . ' characters.'];
    }
    if (mb_strlen($message) > MAX_MESSAGE_LENGTH) {
        http_response_code(422);
        return ['error' => 'Messages cap at ' . MAX_MESSAGE_LENGTH . ' characters.'];
    }

    $ip = client_ip();

    // Flood check: one post per IP per FLOOD_SECONDS.
    $stmt = db()->prepare(
        'SELECT 1 FROM messages
          WHERE ip = :ip AND created_at > (NOW() - INTERVAL :secs SECOND)
          LIMIT 1'
    );
    $stmt->bindValue(':ip', $ip);
    $stmt->bindValue(':secs', FLOOD_SECONDS, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->fetchColumn()) {
        http_response_code(429);
        return ['error' => 'Slow down — one message every ' . FLOOD_SECONDS . ' seconds.'];
    }

    $stmt = db()->prepare(
        'INSERT INTO messages (handle, message, ip) VALUES (:handle, :message, :ip)'
    );
    $stmt->execute([
        ':handle'  => $handle,
        ':message' => $message,
        ':ip'      => $ip,
    ]);

    return ['ok' => true, 'id' => (int) db()->lastInsertId()];
}

/**
 * Trim, normalise newlines, drop control characters.
 * No HTML escaping here — the browser inserts text via textContent,
 * so the raw value is what belongs in the database.
 */
function clean_text(string $value): string
{
    $value = str_replace(["\r\n", "\r"], "\n", $value);
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

    return trim($value);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Parlor</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Parlor</h1>
<p>A simple message board.</p>

<p>
  <label for="handle">Handle</label>
  <input type="text" id="handle" maxlength="<?= MAX_HANDLE_LENGTH ?>" autocomplete="off">
</p>

<ul id="messages"></ul>

<p id="status" role="status"></p>

<form id="composer" autocomplete="off">
  <p>
    <label for="message">Message</label><br>
    <textarea id="message" rows="3" cols="60" maxlength="<?= MAX_MESSAGE_LENGTH ?>"></textarea>
  </p>
  <p><button type="submit" id="send">Send</button></p>
</form>

<script>
(function () {
  'use strict';

  var handleInput = document.getElementById('handle');
  var messageInput = document.getElementById('message');
  var composer = document.getElementById('composer');
  var sendButton = document.getElementById('send');
  var list = document.getElementById('messages');
  var status = document.getElementById('status');

  var lastId = 0;
  var pollTimer = null;
  var POLL_MS = <?= POLL_MS ?>;

  /* ---- handle is remembered locally; there is no account ---- */
  handleInput.value = localStorage.getItem('handle') || '';
  handleInput.addEventListener('input', function () {
    localStorage.setItem('handle', handleInput.value);
  });

  function say(text) {
    status.textContent = text || '';
  }

  function timeLabel(unixSeconds) {
    return new Date(unixSeconds * 1000).toLocaleTimeString();
  }

  function render(messages) {
    if (!messages.length) return;

    // Stick to the bottom only if the reader is already there.
    var atBottom = (window.innerHeight + window.scrollY) >= (document.body.offsetHeight - 50);

    messages.forEach(function (msg) {
      var li = document.createElement('li');

      var time = document.createElement('time');
      time.textContent = timeLabel(msg.ts);

      var who = document.createElement('strong');
      who.textContent = msg.handle;

      var body = document.createElement('span');
      body.textContent = msg.message;

      li.appendChild(time);
      li.appendChild(document.createTextNode(' '));
      li.appendChild(who);
      li.appendChild(document.createTextNode(': '));
      li.appendChild(body);

      list.appendChild(li);

      if (msg.id > lastId) lastId = msg.id;
    });

    if (atBottom) window.scrollTo(0, document.body.scrollHeight);
  }

  function poll() {
    fetch('index.php?action=fetch&since=' + lastId, { cache: 'no-store' })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.messages) render(data.messages);
      })
      .catch(function () {
        say('Lost the connection. Still retrying.');
      })
      .then(schedule);
  }

  function schedule() {
    clearTimeout(pollTimer);
    pollTimer = setTimeout(poll, POLL_MS);
  }

  function send() {
    var handle = handleInput.value.trim();
    var message = messageInput.value.trim();

    if (!handle) {
      say('Pick a handle before posting.');
      handleInput.focus();
      return;
    }
    if (!message) return;

    sendButton.disabled = true;
    say('');

    var body = new URLSearchParams();
    body.set('handle', handle);
    body.set('message', message);

    fetch('index.php?action=post', { method: 'POST', body: body })
      .then(function (res) { return res.json(); })
      .then(function (data) {
        if (data.error) {
          say(data.error);
          return;
        }
        messageInput.value = '';
        clearTimeout(pollTimer);
        poll(); // show it immediately instead of waiting for the next tick
      })
      .catch(function () {
        say('That message did not send. Try again.');
      })
      .then(function () {
        sendButton.disabled = false;
        messageInput.focus();
      });
  }

  composer.addEventListener('submit', function (e) {
    e.preventDefault();
    send();
  });

  // Enter sends, Shift+Enter makes a new line.
  messageInput.addEventListener('keydown', function (e) {
    if (e.key === 'Enter' && !e.shiftKey) {
      e.preventDefault();
      send();
    }
  });

  poll();
})();
</script>

</body>
</html>
