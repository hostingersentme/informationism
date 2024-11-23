<?php
session_start();

// **Add Charset Headers**

header('Content-Type: text/html; charset=UTF-8'); // Ensure UTF-8 encoding

// Error Reporting
ini_set('display_errors', 0); // Prevent errors from being displayed to users
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/imodel_debug.log'); // Separate debug file
error_reporting(E_ALL); // Report all errors

// Function to write to multi-slot debug log
function imodel_write_debug_log($message) {
    $log_file = __DIR__ . '/imodel_debug.log'; // Separate debug file
    $date = date('Y-m-d H:i:s');
    $full_message = "[$date] $message\n";
    
    if (file_put_contents($log_file, $full_message, FILE_APPEND) === false) {
        error_log("Failed to write to imodel_debug.log");
    }
}

// Include the secure API key file
require 'api_key.php';

// Include the centralized system messages
$system_messages = require 'system_messages.php';

// Initialize conversation history
if (!isset($_SESSION['i_model_conversation'])) {
    $_SESSION['i_model_conversation'] = [];
}

// Define system messages as fixed (non-editable)
if (!isset($_SESSION['i_model_system_messages'])) {
    $_SESSION['i_model_system_messages'] = $system_messages;
}

// Initialize settings
if (!isset($_SESSION['i_model_settings'])) {
    $_SESSION['i_model_settings'] = [
        'model' => 'gpt-4o-mini',
        'max_tokens' => 600,
        'temperature' => 0.7,
        'turns' => 6 // Updated from 3 to 6
    ];
}

// **Removed the POST request handling for updating system messages**
// Since prompts are now fixed and non-editable
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>i Model</title>
    <link rel="stylesheet" href="imodel_styles.css">
    <style>
        /* Inline styles for the dropdown arrow rotation */
        .triangle {
            transition: transform 0.3s;
        }
        .triangle.open {
            transform: rotate(180deg);
        }

        /* Styles for floating text from Ava (Removed for simplicity) */
        /* Removed the previous floating text styles to simplify */

        /* Optional: Adjusting message styling if needed */
    </style>
</head>
<body>

<div class="container">
    <!-- Emoji Container within the main content container -->
    <div class="content-container">
        <div id="emojiContainer"></div>

        <!-- Title Box -->
        <div class="title-box">
            <h1 style="color: #6c63ff;">i Model</h1>
        </div>

        <!-- **Removed the Master System Message Box** -->

        <!-- **Removed the Advanced Reasoning Prompts Dropdown** -->



        <!-- Multi Step Interaction -->
        <div>
            <h2 style="color: #6c63ff;">The Personal AI</h2>
            <label for="initialPrompt">What's up?:</label>
            <textarea id="initialPrompt" placeholder="Enter your prompt" maxlength="5000"></textarea>
            <div class="char-counter" id="promptCounter">0/5000 characters</div>
            
            <button id="startBackroomButton">Send</button> <!-- Changed from "Start" to "Send" -->
            <button id="clearBackroomButton">Clear</button>
        </div>

        <!-- Conversation History -->
        <div id="backroomContainer">
            <?php
            foreach ($_SESSION['i_model_conversation'] as $msg) {
                if ($msg['role'] === 'system') {
                    continue;
                } elseif ($msg['role'] === 'user') {
                    echo '<div class="message"><strong>User:</strong> ' . nl2br(htmlspecialchars($msg['content'])) . '</div>';
                } elseif ($msg['role'] === 'assistant') {
                    $model = htmlspecialchars($msg['model'] ?? 'Unknown');
                    $messageClass = $model === 'claude-3-5-sonnet-latest' ? 'claude-message' : 'ai-message';
                    echo '<div class="message ' . $messageClass . '"><strong>AI (' . $model . '):</strong> ' . nl2br(htmlspecialchars($msg['content'])) . '</div>';
                }
            }
            ?>
        </div>
        
                <!-- Site Download Section -->
        <div>
            <h2 style="color: #6c63ff;">Site Download</h2>
            <button id="downloadSiteButton">Download site.zip</button>
            <div id="downloadStatus"></div>
        </div>
        
    </div>

    <!-- Social Media Links -->
    <div class="social-media">
        <a href="https://x.com/RadicalEconomic" target="_blank">Twitter</a>
        <a href="https://www.reddit.com/user/rutan668/" target="_blank">Reddit</a>
        <a href="https://github.com/hostingersentme" target="_blank">Github</a>
    </div>
    
    <!-- User Info -->
    <div class="user-info">
        <p>User info: This is an AI model that features three models 'Ava', 'Gala' and 'Charles' personalisation. For a much more customizable veraion you can use the button to download to your own site.  Give feedback at the social media links above if you find it useful or have any ideas about changes to improve the model. If you run into any difficulties try refreshing the page.</p>
    </div>
</div>

<script>
// Define the emoji to color mapping
const emojiColorMap = {
    '😊': '#FFD700', // Yellow for happiness
    '😢': '#1E90FF', // Blue for sadness
    '😡': '#FF4500', // Orange for anger
    '❤️': '#FF0000', // Red for love
    '😍': '#FF69B4', // Pink for love
    '🤔': '#8A2BE2', // Purple for contemplation
    '😴': '#808080', // Gray for sleepiness
    // Add more mappings as needed
};

function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, function(m) { return map[m]; });
}

// Change Logout button to Login
document.getElementById('loginButton')?.addEventListener('click', () => {
    window.location.href = 'https://informationism.org/register.php';
});

// Character count for user prompt
const initialPrompt = document.getElementById('initialPrompt');
const promptCounter = document.getElementById('promptCounter');

initialPrompt.addEventListener('input', () => {
    promptCounter.textContent = `${initialPrompt.value.length}/5000 characters`;
});

// Function to get system messages for API call
function getSystemMessages() {
    const systemMessages = <?php echo json_encode($_SESSION['i_model_system_messages'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    return systemMessages;
}

// Function to extract emojis from text
function extractEmojis(text) {
    const emojiRegex = /([\u2700-\u27BF]|[\uE000-\uF8FF]|[\uD83C-\uDBFF][\uDC00-\uDFFF])/g;
    return text.match(emojiRegex) || [];
}

// Function to display emojis on screen and change background color
function displayEmojis(emojis) {
    const emojiContainer = document.getElementById('emojiContainer');
    emojiContainer.innerHTML = ''; // Clear previous emojis

    let colorScores = {}; // To tally emotion scores

    // Get container dimensions
    const containerWidth = emojiContainer.offsetWidth;
    const containerHeight = emojiContainer.offsetHeight;

    emojis.forEach(emoji => {
        const emojiElement = document.createElement('span');
        emojiElement.textContent = emoji;
        emojiElement.classList.add('floating-emoji');

        // Random size between 20px and 50px
        const size = Math.floor(Math.random() * 30) + 20;
        emojiElement.style.fontSize = `${size}px`;

        // Random position within the emoji container
        const x = Math.random() * (containerWidth - size);
        const y = Math.random() * (containerHeight - size);

        emojiElement.style.left = `${x}px`;
        emojiElement.style.top = `${y}px`;

        // Random animation duration between 3s and 7s
        const duration = Math.random() * 4 + 3;
        emojiElement.style.animationDuration = `${duration}s`;

        emojiContainer.appendChild(emojiElement);

        // Map emoji to color
        if (emojiColorMap[emoji]) {
            const color = emojiColorMap[emoji];
            colorScores[color] = (colorScores[color] || 0) + 1;
        }
    });

    // Determine the most frequent color
    let dominantColor = null;
    let maxScore = 0;
    for (const color in colorScores) {
        if (colorScores[color] > maxScore) {
            maxScore = colorScores[color];
            dominantColor = color;
        }
    }

    // Change background color if a dominant color is found
    if (dominantColor) {
        document.body.style.backgroundColor = dominantColor;
    } else {
        // Reset to default if no dominant color
        document.body.style.backgroundColor = '#f5f7fa';
    }
}

// Start Backroom Conversation without clearing history
document.getElementById('startBackroomButton').addEventListener('click', async () => {
    let prompt = initialPrompt.value.trim();
    const backroomContainer = document.getElementById('backroomContainer');
    const emojiContainer = document.getElementById('emojiContainer');

    if (!prompt) {
        alert('Please enter an initial prompt.');
        return;
    }

    if (prompt.length > 5000) { // Updated from 700 to 5000
        alert('Prompt exceeds the maximum allowed length of 5000 characters.');
        return;
    }

    // **Do not clear the conversation on the frontend**
    // backroomContainer.innerHTML = '';
    // emojiContainer.innerHTML = '';

    // Append the initial user prompt to the conversation
    const initialMessageDiv = document.createElement('div');
    initialMessageDiv.className = 'message';
    initialMessageDiv.innerHTML = `<strong>User:</strong> ${escapeHtml(prompt)}`;
    backroomContainer.appendChild(initialMessageDiv);

    backroomContainer.scrollTop = backroomContainer.scrollHeight;

    // Disable buttons while processing
    document.getElementById('startBackroomButton').disabled = true;
    document.getElementById('clearBackroomButton').disabled = true;
    document.getElementById('startBackroomButton').textContent = 'Processing...';
    document.getElementById('startBackroomButton').classList.add('loading');

    try {
        // Now, initiate the conversation
        const systemMessages = getSystemMessages();

        const response = await fetch('i_model_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'start_conversation',
                prompt: prompt, 
                turns: <?php echo json_encode($_SESSION['i_model_settings']['turns']); ?>,
                system_messages: systemMessages
            })
        });

        // Check if response is ok
        if (!response.ok) {
            throw new Error(`Server responded with status ${response.status}`);
        }

        const data = await response.json();

        if (data.status === 'error') {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'message error-message';
            errorDiv.innerHTML = `<strong>Error:</strong> ${escapeHtml(data.error)}`;
            backroomContainer.appendChild(errorDiv);
        } else if (data.conversation) {
            // Display updated conversation
            backroomContainer.innerHTML = ''; // Clear to prevent duplicates

            data.conversation.forEach((msg) => {
                if (msg['role'] === 'assistant') {
                    // Identify Slot by name (Ava, Gala, Charles)
                    const slotName = msg['model'];
                    
                    if (slotName === 'Ava') {
                        // Handle Ava's response
                        const emojis = extractEmojis(msg['content']);
                        if (emojis.length > 0) {
                            displayEmojis(emojis);
                        }

                        // Extract remaining text after emojis
                        const nonEmojiText = msg['content'].replace(/([\u2700-\u27BF]|[\uE000-\uF8FF]|[\uD83C-\uDBFF][\uDC00-\uDFFF])/g, '').trim();
                        if (nonEmojiText) {
                            // Display the non-emoji text in the regular conversation history
                            const textMessageDiv = document.createElement('div');
                            textMessageDiv.className = 'message ai-message';
                            textMessageDiv.innerHTML = `<strong>AI (${escapeHtml(slotName)}):</strong> ${escapeHtml(nonEmojiText).replace(/\n/g, '<br>')}`;
                            backroomContainer.appendChild(textMessageDiv);
                        }

                        // Do not append Ava's textual content again
                        return; // Exit early
                    }

                    // For other assistant messages, display normally
                    const msgDiv = document.createElement('div');
                    const model = escapeHtml(msg.model ?? 'Unknown');
                    const messageClass = model === 'claude-3-5-sonnet-latest' ? 'claude-message' : 'ai-message';
                    msgDiv.className = `message ${messageClass}`;
                    msgDiv.innerHTML = `<strong>AI (${model}):</strong> ${escapeHtml(msg['content']).replace(/\n/g, '<br>')}`;
                    backroomContainer.appendChild(msgDiv);
                } else if (msg['role'] === 'user') {
                    const msgDiv = document.createElement('div');
                    msgDiv.className = 'message';
                    msgDiv.innerHTML = `<strong>User:</strong> ${escapeHtml(msg['content'])}`;
                    backroomContainer.appendChild(msgDiv);
                }
            });

            // Scroll to the bottom of the conversation
            backroomContainer.scrollTop = backroomContainer.scrollHeight;

            // **Automatically Clear the Prompt Field**
            initialPrompt.value = ''; // Clear the prompt
            promptCounter.textContent = '0/5000 characters'; // Reset character counter
        }

    } catch (error) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'message error-message';
        errorDiv.innerHTML = `<strong>Error:</strong> ${escapeHtml(error.message)}`;
        backroomContainer.appendChild(errorDiv);
    } finally {
        // Re-enable buttons
        document.getElementById('startBackroomButton').disabled = false;
        document.getElementById('clearBackroomButton').disabled = false;
        document.getElementById('startBackroomButton').textContent = 'Send'; // Ensure button text is updated
        document.getElementById('startBackroomButton').classList.remove('loading');
    }
});

// Clear Conversation
document.getElementById('clearBackroomButton').addEventListener('click', async () => {
    const backroomContainer = document.getElementById('backroomContainer');
    const emojiContainer = document.getElementById('emojiContainer');

    if (!confirm('Are you sure you want to clear the conversation?')) {
        return;
    }

    try {
        const response = await fetch('i_model_api.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'clear_backroom' })
        });

        const data = await response.json();

        if (data.status === 'success') {
            backroomContainer.innerHTML = '';
            emojiContainer.innerHTML = '';
            alert('Conversation cleared successfully.');
            // Reset background color to default
            document.body.style.backgroundColor = '#f5f7fa';
        } else {
            alert('Failed to clear the conversation.');
        }
    } catch {
        alert('Error clearing the conversation.');
    }
});

// Handle Site Download
document.getElementById('downloadSiteButton').addEventListener('click', () => {
    const downloadStatus = document.getElementById('downloadStatus');
    downloadStatus.style.color = 'black';
    downloadStatus.textContent = 'Preparing download...';

    // Create a temporary link element
    const link = document.createElement('a');
    link.href = 'site.zip'; // Ensure site.zip is in the same directory
    link.download = 'site.zip';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);

    downloadStatus.style.color = 'green';
    downloadStatus.textContent = 'Download initiated.';
});
</script>
</body>
</html>