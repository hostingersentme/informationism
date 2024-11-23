<?php
session_start();

// **Add Charset Headers**

header('Content-Type: application/json; charset=UTF-8'); // Ensure UTF-8 encoding

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

// Assign system messages to session if not already set
if (!isset($_SESSION['i_model_system_messages'])) {
    $_SESSION['i_model_system_messages'] = $system_messages;
}

// Initialize conversation history
if (!isset($_SESSION['i_model_conversation'])) {
    $_SESSION['i_model_conversation'] = [];
    imodel_write_debug_log("Conversation history initialized for session: " . session_id());
}

// Function to handle memory (only for Ava messages)
function handle_memory($content, $model) {
    if ($model !== 'Ava') {
        // Only Ava processes memories
        return $content;
    }

    // Ensure the 'memories' directory exists
    $memories_dir = __DIR__ . '/memories';
    if (!is_dir($memories_dir)) {
        if (!mkdir($memories_dir, 0755, true)) {
            imodel_write_debug_log("Failed to create memories directory: $memories_dir");
            return $content;
        }
    }

    // Use session ID to have separate memory for each user
    $session_id = session_id();
    $memory_file = $memories_dir . "/memory_{$session_id}.json";

    // Make regex case-insensitive and handle possible whitespace
    if (preg_match_all('/<memory>\s*(.*?)\s*<\/memory>/si', $content, $matches)) {
        $memories = $matches[1];
        imodel_write_debug_log("Found " . count($memories) . " memory entries from Ava.");
        foreach ($memories as $memory) {
            $memory = trim($memory);
            imodel_write_debug_log("Captured memory before validation: '$memory'");
            if ($memory === '') {
                imodel_write_debug_log("Skipped empty memory.");
                continue; // Skip empty memories
            }

            // Initialize memory type and content
            $type = '';
            $number = 0;
            $content_text = '';

            // Check if memory matches the expected format
            if (preg_match('/^(user_info|engagement_strategies):(\d+):(.+)$/', $memory, $type_matches)) {
                $type = $type_matches[1];
                $number = intval($type_matches[2]);
                $content_text = trim($type_matches[3]);

                if ($type !== 'user_info' && $type !== 'engagement_strategies') {
                    imodel_write_debug_log("Invalid memory type: '$type'. Skipping.");
                    continue;
                }

                if ($number <= 0) {
                    imodel_write_debug_log("Invalid memory number: '$number'. Skipping.");
                    continue;
                }
            } else {
                // If format is invalid, treat it as a 'general' memory
                $type = 'general';
                $content_text = $memory;

                // Determine the next available number for 'general' memories
                $existing_number = get_next_memory_number($memory_file, $type);
                $number = $existing_number;
            }

            // Load existing memories
            $memory_data = [
                'user_info' => [],
                'engagement_strategies' => [],
                'general' => []
            ];

            if (file_exists($memory_file)) {
                $memory_content = file_get_contents($memory_file);
                $memory_data = json_decode($memory_content, true);
                if (!is_array($memory_data)) {
                    $memory_data = [
                        'user_info' => [],
                        'engagement_strategies' => [],
                        'general' => []
                    ];
                }
            }

            // Ensure the category exists
            if (!isset($memory_data[$type])) {
                $memory_data[$type] = [];
            }

            // Update or add the memory
            $memory_data[$type][$number] = $content_text;
            imodel_write_debug_log("Memory updated: Type='$type', Number='$number', Content='$content_text'");

            // Save the updated memories
            if (file_put_contents($memory_file, json_encode($memory_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) === false) {
                imodel_write_debug_log("Failed to write memory: Type='$type', Number='$number'");
            } else {
                imodel_write_debug_log("Memory saved: Type='$type', Number='$number'");
            }
        }
    } else {
        imodel_write_debug_log("No <memory> tags found in Ava's content.");
    }
    // Remove memory tags from content, case-insensitive and handle possible whitespace
    $clean_content = preg_replace('/<memory>\s*.*?\s*<\/memory>/si', '', $content);
    imodel_write_debug_log("Content after removing memory tags: '$clean_content'");
    return $clean_content;
}

// Function to get the next available memory number for a given type
function get_next_memory_number($memory_file, $type) {
    if (!file_exists($memory_file)) {
        return 1;
    }

    $memory_content = file_get_contents($memory_file);
    $memories = json_decode($memory_content, true);
    if (!is_array($memories) || !isset($memories[$type])) {
        return 1;
    }

    if (empty($memories[$type])) {
        return 1;
    }

    // Get the highest existing number and increment
    $numbers = array_map('intval', array_keys($memories[$type]));
    return max($numbers) + 1;
}

// Function to load memories
function load_memories($session_id) {
    $memory_file = __DIR__ . "/memories/memory_{$session_id}.json";
    if (file_exists($memory_file)) {
        $memory_content = file_get_contents($memory_file);
        $memories = json_decode($memory_content, true);
        if (is_array($memories)) {
            return $memories;
        }
    }
    return [
        'user_info' => [],
        'engagement_strategies' => [],
        'general' => []
    ];
}

// Initialize memory file if not exists
$memories_dir = __DIR__ . '/memories';
if (!is_dir($memories_dir)) {
    if (!mkdir($memories_dir, 0755, true)) {
        imodel_write_debug_log("Failed to create memories directory: $memories_dir");
    }
}
$session_id = session_id();
$memory_file = $memories_dir . "/memory_{$session_id}.json";
if (!file_exists($memory_file)) {
    file_put_contents($memory_file, json_encode(['user_info' => [], 'engagement_strategies' => [], 'general' => []], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    imodel_write_debug_log("Created new memory file for session: $session_id.");
}

header('Content-Type: application/json; charset=UTF-8'); // Ensure UTF-8 encoding

$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Remove 'system_messages' from incoming data as we're fetching it from the session
// No need to process 'system_messages' from frontend

if (!$data || !isset($data['action'])) {
    echo json_encode(['status' => 'error', 'error' => 'Invalid request.']);
    exit();
}

$action = $data['action'];

switch ($action) {
    case 'start_conversation':
        // Ensure required parameters are present
        if (!isset($data['prompt'], $data['turns'])) {
            echo json_encode(['status' => 'error', 'error' => 'Incomplete conversation data.']);
            exit();
        }

        // Fetch system_messages from session
        $system_messages = $_SESSION['i_model_system_messages'];

        // Strip any <memory></memory> tags from user prompt
        $prompt = preg_replace('/<memory>\s*.*?\s*<\/memory>/si', '', trim($data['prompt']));
        $turns = intval($data['turns']);

        if (empty($prompt)) {
            echo json_encode(['status' => 'error', 'error' => 'Prompt cannot be empty.']);
            exit();
        }

        if ($turns <= 0) {
            echo json_encode(['status' => 'error', 'error' => 'Invalid number of turns.']);
            exit();
        }

        if (mb_strlen($prompt, 'UTF-8') > 5000) { // Updated from 700 to 5000
            echo json_encode(['status' => 'error', 'error' => 'Prompt exceeds the maximum allowed length of 5000 characters.']);
            exit();
        }

        // Append the initial user prompt to the conversation
        $_SESSION['i_model_conversation'][] = ['role' => 'user', 'content' => $prompt];
        
        imodel_write_debug_log("Conversation started with prompt: $prompt");

        // Define models for slots
        $selected_models = [
            'Ava' => 'gpt-4o-mini',    // Emotion
            'Gala' => 'gpt-4o-mini',   // Regular Responder
            'Charles' => 'gpt-4o-mini' // Setup Assistant
        ];

        // Initialize a queue for slot processing
        $slots_queue = ['Ava', 'Gala']; // Automatically call Ava and Gala

        // Load memories
        $memories = load_memories($session_id);
        $memory_message = "";

        // Compile memory messages
        foreach (['user_info', 'engagement_strategies', 'general'] as $type) { // Added 'general'
            if (!empty($memories[$type])) {
                $memory_message .= ucfirst(str_replace('_', ' ', $type)) . ":\n";
                foreach ($memories[$type] as $num => $info) {
                    $memory_message .= "{$num}. {$info}\n";
                }
                $memory_message .= "\n";
            }
        }

        // Process slots in the queue
        while (!empty($slots_queue) && $turns > 0) {
            $current_slot = array_shift($slots_queue);
            $model = $selected_models[$current_slot];
            $system_message = $system_messages[$current_slot];
            $master_message = $system_messages['master'];

            // Prepare messages for the current slot
            $messages = [
                ['role' => 'system', 'content' => $master_message],
                ['role' => 'system', 'content' => $system_message],
            ];

            // Include memories as a system message
            if (!empty($memory_message)) {
                $messages[] = ['role' => 'system', 'content' => $memory_message];
            }

            // Append conversation history to messages
            foreach ($_SESSION['i_model_conversation'] as $msg) {
                $messages[] = ['role' => $msg['role'], 'content' => $msg['content']];
            }

            // Make API call for the current slot
            $assistant_reply = make_api_call($model, $messages);

            imodel_write_debug_log("Received assistant reply from slot '$current_slot': '$assistant_reply'");

            // Process memory from the assistant's reply (only Ava)
            $processed_reply = handle_memory($assistant_reply, $current_slot);

            imodel_write_debug_log("Processed assistant reply after memory handling: '$processed_reply'");

            // Append Slot's response to conversation history
            $_SESSION['i_model_conversation'][] = [
                'role' => 'assistant',
                'model' => $current_slot,
                'content' => $processed_reply
            ];

            imodel_write_debug_log("Appended assistant response from '$current_slot' to conversation history.");

            // Check if the current slot's response includes a call to another slot
            if (preg_match('/<call>(\w+)<\/call>/', $assistant_reply, $matches)) {
                $called_slot = $matches[1];
                if (in_array($called_slot, ['Ava', 'Gala', 'Charles'])) {
                    imodel_write_debug_log("Invoking called slot: $called_slot.");
                    $slots_queue[] = $called_slot;
                } else {
                    imodel_write_debug_log("Invalid slot called: $called_slot.");
                }
            }

            $turns--;
        }

        echo json_encode(['status' => 'success', 'conversation' => $_SESSION['i_model_conversation']]);
        break;

    case 'clear_backroom':
        // Clear the conversation
        $_SESSION['i_model_conversation'] = [];
        imodel_write_debug_log("Conversation cleared by user for session: $session_id.");
        echo json_encode(['status' => 'success']);
        break;

    default:
        echo json_encode(['status' => 'error', 'error' => 'Unknown action.']);
        break;
}

/**
 * Function to make API calls based on the model and messages.
 */
function make_api_call($model, $messages) {
    global $api_endpoints;

    // Define API endpoints and settings
    $api_endpoints = [
        'gpt-4o-mini' => [
            'url' => 'https://api.openai.com/v1/chat/completions',
            'headers' => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . API_KEY
            ],
            'model' => 'gpt-4o-mini',
            'max_tokens_limit' => 4096 // Example limit; adjust based on actual API documentation
        ],
        // Add other models if needed...
    ];

    // Check if the selected model is supported
    if (!array_key_exists($model, $api_endpoints)) {
        imodel_write_debug_log("Unsupported model selected: $model");
        return "Unsupported model selected.";
    }

    // Prepare the payload based on the model
    $payload = [
        "model" => $api_endpoints[$model]['model'],
        "messages" => $messages,
        "max_tokens" => 5000, // Updated to accommodate longer responses
        "temperature" => 0.7 // Adjust as needed
    ];

    // Initialize cURL
    $ch = curl_init($api_endpoints[$model]['url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $api_endpoints[$model]['headers']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));

    // Execute the API request
    $response = curl_exec($ch);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if (curl_errno($ch)) {
        imodel_write_debug_log("cURL error: " . curl_error($ch));
        curl_close($ch);
        return "API request failed due to a network error.";
    }

    curl_close($ch);

    // Handle non-200 responses
    if ($http_status !== 200) {
        imodel_write_debug_log("API request failed with status code $http_status: $response");
        return "API request failed with status code $http_status.";
    }

    // Decode the response
    $decoded_response = json_decode($response, true);

    // Extract assistant's reply
    if (isset($decoded_response['choices'][0]['message']['content'])) {
        $ai_response = trim($decoded_response['choices'][0]['message']['content']);
        // No stripping of memory tags here; handle_memory already processes assistant messages
        return $ai_response;
    } else {
        imodel_write_debug_log("GPT-4o-mini response missing content: " . json_encode($decoded_response));
        return "No content in response.";
    }
}
?>