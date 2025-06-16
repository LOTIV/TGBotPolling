<?php

define('BOT_TOKEN', 'token');
define('TIMER_FILE', 'timer.txt');
define('COUNT_FILE', 'count.txt');
define('TAG_USER_FILE', 'tag_user.txt');
define('SPM_STATUS_FILE', 'spm_status.txt');

// Define 10 message files
for ($i = 1; $i <= 10; $i++) {
    define("MESSAGE_FILE_$i", "stored_message$i.txt");
}

function sendMessage($chat_id, $text, $reply_markup = null) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $tag_user = file_exists(TAG_USER_FILE) ? file_get_contents(TAG_USER_FILE) : '';
    $text_with_tag = $text . (!empty($tag_user) ? "\n\n" . $tag_user : '');
    $post_fields = [
        'chat_id' => $chat_id,
        'text' => $text_with_tag,
        'parse_mode' => 'HTML'
    ];
    if ($reply_markup) {
        $post_fields['reply_markup'] = json_encode($reply_markup);
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result, true);
    file_put_contents('debug.log', "Send message response: " . $result . "\n", FILE_APPEND);
    return $response;
}

function getUpdates($offset = null) {
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/getUpdates";
    if ($offset) {
        $url .= "?offset=$offset";
    }
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    $response = json_decode($result, true);
    file_put_contents('debug.log', "Get updates response: " . $result . "\n", FILE_APPEND);
    return $response;
}

function stopSpam($chat_id) {
    if (file_exists(SPM_STATUS_FILE)) {
        unlink(SPM_STATUS_FILE);
        sendMessage($chat_id, "Spam process stopped!");
    }
}

function processUpdate($update) {
    $admin_chat_id = 'put admin id here'; 
    
    file_put_contents('debug.log', "Update received: " . json_encode($update) . "\n", FILE_APPEND);
    
    if (isset($update['message'])) {
        $message = $update['message'];
        $chat_id = $message['chat']['id'];
        $text = $message['text'] ?? '';
        $user_id = $message['from']['id'];
        
        // Stop spam on any new message
        stopSpam($chat_id);
        
        if ((string)$user_id !== $admin_chat_id) {
            file_put_contents('debug.log', "Ignored - User $user_id is not admin $admin_chat_id\n", FILE_APPEND);
            return;
        }
        
        file_put_contents('debug.log', "Processing command '$text' from admin $user_id\n", FILE_APPEND);
        
        // Handle /set command
        if (strpos($text, '/set') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                sendMessage($chat_id, "Settings updated successfully by admin!\nChat ID: $chat_id");
            } else {
                sendMessage($chat_id, "The /set command can only be used in groups!");
            }
        }
        
        // Handle /setmessage[1-10] commands
        for ($i = 1; $i <= 10; $i++) {
            if (strpos($text, "/setmessage$i") === 0) {
                $chat_type = $message['chat']['type'];
                if ($chat_type === 'group' || $chat_type === 'supergroup') {
                    $custom_message = trim(substr($text, 11 + strlen((string)$i)));
                    if (!empty($custom_message)) {
                        file_put_contents(constant("MESSAGE_FILE_$i"), $custom_message);
                        sendMessage($chat_id, "Message $i set successfully!\nMessage: \"$custom_message\"");
                    } else {
                        $stored_message = file_exists(constant("MESSAGE_FILE_$i")) ? file_get_contents(constant("MESSAGE_FILE_$i")) : "No message set";
                        sendMessage($chat_id, "Current stored message $i: \"$stored_message\"\nPlease provide a message after /setmessage$i");
                    }
                } else {
                    sendMessage($chat_id, "The /setmessage$i command can only be used in groups!");
                }
                return;
            }
        }
        
        // Handle /getmessage command
        if (strpos($text, '/getmessage') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $response = "Stored messages:\n";
                for ($i = 1; $i <= 10; $i++) {
                    $stored_message = file_exists(constant("MESSAGE_FILE_$i")) ? file_get_contents(constant("MESSAGE_FILE_$i")) : "No message set";
                    $response .= "Message $i: \"$stored_message\"\n";
                }
                sendMessage($chat_id, $response);
            } else {
                sendMessage($chat_id, "The /getmessage command can only be used in groups!");
            }
        }
        
        // Handle /settimer command
        if (strpos($text, '/settimer') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $timer = trim(substr($text, 9));
                if (is_numeric($timer) && $timer >= 1) {
                    file_put_contents(TIMER_FILE, $timer);
                    sendMessage($chat_id, "Timer set successfully to $timer seconds!");
                } else {
                    $current_timer = file_exists(TIMER_FILE) ? file_get_contents(TIMER_FILE) : "2 (default)";
                    sendMessage($chat_id, "Current timer: $current_timer seconds\nPlease provide a number >= 1 after /settimer");
                }
            } else {
                sendMessage($chat_id, "The /settimer command can only be used in groups!");
            }
        }
        
        // Handle /setcount command
        if (strpos($text, '/setcount') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $count = trim(substr($text, 9));
                if (is_numeric($count) && $count >= 1) {
                    file_put_contents(COUNT_FILE, $count);
                    sendMessage($chat_id, "Message count set successfully to $count!");
                } else {
                    $current_count = file_exists(COUNT_FILE) ? file_get_contents(COUNT_FILE) : "5 (default)";
                    sendMessage($chat_id, "Current count: $current_count\nPlease provide a number >= 1 after /setcount");
                }
            } else {
                sendMessage($chat_id, "The /setcount command can only be used in groups!");
            }
        }
        
        // Handle /startspm command
        if (strpos($text, '/startspm') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $messages = [];
                for ($i = 1; $i <= 10; $i++) {
                    if (file_exists(constant("MESSAGE_FILE_$i"))) {
                        $messages[] = file_get_contents(constant("MESSAGE_FILE_$i"));
                    }
                }
                if (!empty($messages)) {
                    $timer = file_exists(TIMER_FILE) ? (int)file_get_contents(TIMER_FILE) : 2;
                    $count = file_exists(COUNT_FILE) ? (int)file_get_contents(COUNT_FILE) : 5;
                    
                    // Save spam status
                    file_put_contents(SPM_STATUS_FILE, json_encode([
                        'chat_id' => $chat_id,
                        'messages' => $messages,
                        'timer' => $timer,
                        'count' => $count,
                        'current_cycle' => 0,
                        'current_message' => 0,
                        'next_send_time' => time()
                    ]));
                    
                    sendMessage($chat_id, "Starting to send " . count($messages) . " stored messages $count times with $timer-second intervals...");
                } else {
                    sendMessage($chat_id, "No messages set! Please use /setmessage1 to /setmessage10 first.");
                }
            } else {
                sendMessage($chat_id, "The /startspm command can only be used in groups!");
            }
        }
        
        // Handle /stopspm command
        if (strpos($text, '/stopspm') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                stopSpam($chat_id);
            } else {
                sendMessage($chat_id, "The /stopspm command can only be used in groups!");
            }
        }
        
        // Handle /deletemsg command
        if (strpos($text, '/deletemsg') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $number = trim(substr($text, 10));
                if (is_numeric($number) && $number >= 1 && $number <= 10) {
                    $file = constant("MESSAGE_FILE_$number");
                    if (file_exists($file)) {
                        unlink($file);
                        sendMessage($chat_id, "Stored message $number deleted successfully!");
                    } else {
                        sendMessage($chat_id, "No message $number exists to delete!");
                    }
                } else {
                    sendMessage($chat_id, "Please provide a number between 1 and 10 after /deletemsg (e.g., /deletemsg 1)");
                }
            } else {
                sendMessage($chat_id, "The /deletemsg command can only be used in groups!");
            }
        }
        
        // Handle /cls command
        if (strpos($text, '/cls') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $deleted = false;
                for ($i = 1; $i <= 10; $i++) {
                    if (file_exists(constant("MESSAGE_FILE_$i"))) {
                        unlink(constant("MESSAGE_FILE_$i"));
                        $deleted = true;
                    }
                }
                if (file_exists(TIMER_FILE)) {
                    unlink(TIMER_FILE);
                    $deleted = true;
                }
                if (file_exists(COUNT_FILE)) {
                    unlink(COUNT_FILE);
                    $deleted = true;
                }
                if (file_exists(TAG_USER_FILE)) {
                    unlink(TAG_USER_FILE);
                    $deleted = true;
                }
                if ($deleted) {
                    sendMessage($chat_id, "All stored settings cleared successfully!");
                } else {
                    sendMessage($chat_id, "No settings exist to clear!");
                }
            } else {
                sendMessage($chat_id, "The /cls command can only be used in groups!");
            }
        }
        
        // Handle /usertg command
        if (strpos($text, '/usertg') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $tag_user = trim(substr($text, 7));
                if (!empty($tag_user)) {
                    if (strpos($tag_user, '@') !== 0) {
                        $tag_user = '@' . $tag_user;
                    }
                    file_put_contents(TAG_USER_FILE, $tag_user);
                    sendMessage($chat_id, "User tag set successfully to: \"$tag_user\"");
                } else {
                    $current_tag = file_exists(TAG_USER_FILE) ? file_get_contents(TAG_USER_FILE) : "No user tag set";
                    sendMessage($chat_id, "Current user tag: \"$current_tag\"\nPlease provide a username after /usertg (e.g., /usertg @username)");
                }
            } else {
                sendMessage($chat_id, "The /usertg command can only be used in groups!");
            }
        }
        
        // Handle /panelpv command
        if (strpos($text, '/panelpv') === 0) {
            $chat_type = $message['chat']['type'];
            if ($chat_type === 'group' || $chat_type === 'supergroup') {
                $inline_keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => "Msg's", 'callback_data' => 'msgs'],
                            ['text' => 'Bot Info', 'callback_data' => 'botinfo']
                        ]
                    ]
                ];
                sendMessage($admin_chat_id, "Here’s your control panel:", $inline_keyboard);
                sendMessage($chat_id, "Panel sent to your private chat!");
            } else {
                sendMessage($chat_id, "The /panelpv command can only be used in groups!");
            }
        }
    } elseif (isset($update['callback_query'])) {
        $callback = $update['callback_query'];
        $chat_id = $callback['message']['chat']['id'];
        $data = $callback['data'];
        switch ($data) {
            case 'msgs':
                $response = "Stored messages:\n";
                for ($i = 1; $i <= 10; $i++) {
                    $stored_message = file_exists(constant("MESSAGE_FILE_$i")) ? file_get_contents(constant("MESSAGE_FILE_$i")) : "No message set";
                    $response .= "Message $i: \"$stored_message\"\n";
                }
                sendMessage($chat_id, $response);
                break;
            case 'botinfo':
                $timer = file_exists(TIMER_FILE) ? file_get_contents(TIMER_FILE) : "2 (default)";
                $count = file_exists(COUNT_FILE) ? file_get_contents(COUNT_FILE) : "5 (default)";
                $tag_user = file_exists(TAG_USER_FILE) ? file_get_contents(TAG_USER_FILE) : "No user tag set";
                $info = "Bot Info:\n- Timer: $timer seconds\n- Count: $count cycles\n- Tagged User: $tag_user";
                sendMessage($chat_id, $info);
                break;
        }
    }
}

// Handle AJAX request from the control panel (unchanged)
if (isset($_POST['ajax'])) {
    $chat_id = $_POST['chat_id'] ?? '';
    $messages = [
        $_POST['message1'] ?? '',
        $_POST['message2'] ?? '',
        $_POST['message3'] ?? '',
        $_POST['message4'] ?? '',
        $_POST['message5'] ?? '',
        $_POST['message6'] ?? '',
        $_POST['message7'] ?? '',
        $_POST['message8'] ?? '',
        $_POST['message9'] ?? '',
        $_POST['message10'] ?? ''
    ];
    $intervals = [
        (int)($_POST['interval1'] ?? 0),
        (int)($_POST['interval2'] ?? 0),
        (int)($_POST['interval3'] ?? 0),
        (int)($_POST['interval4'] ?? 0),
        (int)($_POST['interval5'] ?? 0),
        (int)($_POST['interval6'] ?? 0),
        (int)($_POST['interval7'] ?? 0),
        (int)($_POST['interval8'] ?? 0),
        (int)($_POST['interval9'] ?? 0),
        (int)($_POST['interval10'] ?? 0)
    ];
    $index = (int)($_POST['index'] ?? 0);
    $usernames = $_POST['usernames'] ?? '';
    $default_interval = (int)($_POST['default_interval'] ?? 1000);
    
    if (!empty($chat_id) && !empty($messages[0])) {
        $message = $messages[$index % count(array_filter($messages))];
        if (!empty($message)) {
            $tags = '';
            if (!empty($usernames)) {
                $username_array = array_filter(array_map('trim', explode(',', $usernames)));
                foreach ($username_array as $username) {
                    $username = ltrim($username, '@');
                    $tags .= "@$username ";
                }
                $message .= "\n\n" . trim($tags);
            }
            
            $response = sendMessage($chat_id, $message);
            echo json_encode([
                'status' => $response['ok'] ? 'success' : 'error',
                'message' => $response['ok'] ? 'Message sent!' : $response['description'],
                'nextInterval' => $intervals[$index % count(array_filter($messages))] ?: $default_interval
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'No message at this index']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Missing chat ID or Message 1']);
    }
    exit;
}

// Control Panel (unchanged)
if (isset($_GET['panel'])) {
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Telegram Bot Control Panel</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .message-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .message-text { flex: 2; min-width: 300px; }
        .message-interval { flex: 1; min-width: 150px; }
        label { display: block; margin-bottom: 5px; }
        input, textarea, select { width: 100%; padding: 8px; margin-bottom: 10px; box-sizing: border-box; }
        button { background-color: #0088cc; color: white; padding: 10px 20px; border: none; cursor: pointer; }
        button:hover { background-color: #006699; }
        button:disabled { background-color: #cccccc; cursor: not-allowed; }
        .status { margin-top: 15px; padding: 10px; border-radius: 5px; }
        .success { background-color: #dff0d8; color: #3c763d; }
        .error { background-color: #f2dede; color: #a94442; }
    </style>
</head>
<body>
    <h1>Telegram Bot Control Panel</h1>
    <form id="messageForm">
        <div class="form-group">
            <label for="chat_id">Chat ID:</label>
            <input type="text" id="chat_id" name="chat_id" placeholder="Enter Telegram Chat ID" required>
        </div>
        <?php for ($i = 1; $i <= 10; $i++): ?>
        <div class="form-group message-group">
            <div class="message-text">
                <label for="message<?php echo $i; ?>">Message <?php echo $i; ?><?php echo $i === 1 ? ' (required)' : ' (optional)'; ?>:</label>
                <textarea id="message<?php echo $i; ?>" name="message<?php echo $i; ?>" rows="3" placeholder="Enter message <?php echo $i; ?>"<?php echo $i === 1 ? ' required' : ''; ?>></textarea>
            </div>
            <div class="message-interval">
                <label for="interval<?php echo $i; ?>">Interval (ms):</label>
                <input type="number" id="interval<?php echo $i; ?>" name="interval<?php echo $i; ?>" min="0" placeholder="Custom delay" step="250">
            </div>
        </div>
        <?php endfor; ?>
        <div class="form-group">
            <label for="usernames">Tag Users (comma-separated, e.g., user1, @user2):</label>
            <input type="text" id="usernames" name="usernames" placeholder="Enter usernames to tag">
        </div>
        <div class="form-group">
            <label for="count">Number of Messages:</label>
            <input type="number" id="count" name="count" min="1" value="5" required>
        </div>
        <div class="form-group">
            <label for="default_interval">Default Interval (ms):</label>
            <input type="number" id="default_interval" name="default_interval" min="100" value="1000" placeholder="Enter default delay" required>
        </div>
        <button type="button" id="sendButton">Send Messages</button>
        <button type="button" id="stopButton" style="display:none;">Stop Sending</button>
    </form>
    <div id="status" class="status"></div>

    <script>
        let intervalId = null;
        const sendButton = document.getElementById('sendButton');
        const stopButton = document.getElementById('stopButton');
        const statusDiv = document.getElementById('status');

        function sendNextMessage(chatId, messages, intervals, usernames, count, defaultInterval, sent = 0) {
            if (sent >= count) {
                stopSending();
                return;
            }

            const index = sent % messages.length;
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `ajax=1&chat_id=${encodeURIComponent(chatId)}&${messages.map((m, i) => `message${i + 1}=${encodeURIComponent(m)}`).join('&')}&${intervals.map((int, i) => `interval${i + 1}=${encodeURIComponent(int)}`).join('&')}&usernames=${encodeURIComponent(usernames)}&index=${index}&default_interval=${defaultInterval}`
            })
            .then(response => response.json())
            .then(data => {
                statusDiv.textContent = `${data.message} (Sent: ${sent + 1}/${count})`;
                statusDiv.className = `status ${data.status}`;

                if (data.status === 'success') {
                    const nextDelay = data.nextInterval || defaultInterval;
                    intervalId = setTimeout(() => sendNextMessage(chatId, messages, intervals, usernames, count, defaultInterval, sent + 1), nextDelay);
                } else {
                    stopSending();
                }
            })
            .catch(error => {
                statusDiv.textContent = `Error: ${error.message}`;
                statusDiv.className = 'status error';
                stopSending();
            });
        }

        sendButton.addEventListener('click', () => {
            const chatId = document.getElementById('chat_id').value;
            const messages = Array.from({length: 10}, (_, i) => document.getElementById(`message${i + 1}`).value).filter(m => m.trim());
            const intervals = Array.from({length: 10}, (_, i) => parseInt(document.getElementById(`interval${i + 1}`).value) || 0);
            const usernames = document.getElementById('usernames').value;
            const count = parseInt(document.getElementById('count').value);
            const defaultInterval = parseInt(document.getElementById('default_interval').value);

            if (!chatId || !messages.length || !count || !defaultInterval) {
                statusDiv.textContent = 'Please fill in required fields (Chat ID, at least Message 1, Count, Default Interval).';
                statusDiv.className = 'status error';
                return;
            }

            if (defaultInterval < 100) {
                statusDiv.textContent = 'Default Interval must be at least 100 ms.';
                statusDiv.className = 'status error';
                return;
            }

            sendButton.disabled = true;
            stopButton.style.display = 'inline';
            statusDiv.textContent = 'Sending messages...';
            statusDiv.className = 'status';

            sendNextMessage(chatId, messages, intervals, usernames, count, defaultInterval);
        });

        stopButton.addEventListener('click', stopSending);

        function stopSending() {
            clearTimeout(intervalId);
            intervalId = null;
            sendButton.disabled = false;
            stopButton.style.display = 'none';
            if (!statusDiv.className.includes('error')) {
                statusDiv.textContent += ' - Stopped.';
            }
        }
    </script>
</body>
</html>

<?php
    exit;
}

// Main loop for polling updates and handling spam
$offset = null;
while (true) {
    // Handle spam process
    if (file_exists(SPM_STATUS_FILE)) {
        $spm_status = json_decode(file_get_contents(SPM_STATUS_FILE), true);
        if ($spm_status && time() >= $spm_status['next_send_time']) {
            $chat_id = $spm_status['chat_id'];
            $messages = $spm_status['messages'];
            $timer = $spm_status['timer'];
            $count = $spm_status['count'];
            $current_cycle = $spm_status['current_cycle'];
            $current_message = $spm_status['current_message'];
            
            if ($current_cycle < $count) {
                $msg = $messages[$current_message];
                sendMessage($chat_id, $msg);
                
                $current_message++;
                if ($current_message >= count($messages)) {
                    $current_message = 0;
                    $current_cycle++;
                }
                
                // Update spam status
                if ($current_cycle < $count) {
                    file_put_contents(SPM_STATUS_FILE, json_encode([
                        'chat_id' => $chat_id,
                        'messages' => $messages,
                        'timer' => $timer,
                        'count' => $count,
                        'current_cycle' => $current_cycle,
                        'current_message' => $current_message,
                        'next_send_time' => time() + $timer
                    ]));
                } else {
                    unlink(SPM_STATUS_FILE);
                    sendMessage($chat_id, "Finished sending " . count($messages) . " stored messages $count times!");
                }
            }
        }
    }
    
    // Process updates
    $updates = getUpdates($offset);
    if ($updates['ok'] && !empty($updates['result'])) {
        foreach ($updates['result'] as $update) {
            $offset = $update['update_id'] + 1;
            processUpdate($update);
        }
    }
    
    sleep(1); // Prevent excessive API calls
}
?>p(1); // Prevent excessive API calls
}
?>