// Function to get the Master Prompt from the activated snippet
if (!function_exists('get_master_prompt')) {
    function get_master_prompt() {
        return ''; // Fallback in case the snippet is not activated
    }
}

function chunk_text($text, $chunk_size) {
    $chunks = [];
    $length = strlen($text);

    for ($i = 0; $i < $length; $i += $chunk_size) {
        $chunks[] = substr($text, $i, $chunk_size);
    }

    return $chunks;
}

// Function to handle the OpenAI API request with chunking
function handle_openai_api_request($job_description) {
    $api_key = 'key here'; // Replace with your OpenAI API key
    $endpoint = 'https://api.openai.com/v1/chat/completions';
    $master_prompt = get_master_prompt();

    // Set the maximum number of tokens for each request
    $max_tokens = 1000; // Adjust based on your requirements
    $chunk_size = 4000; // Define chunk size to fit within the API limits

    // Split the job description into chunks
    $chunks = chunk_text($job_description, $chunk_size);
    $responses = [];

    foreach ($chunks as $chunk) {
        $data = array(
            'model' => 'gpt-4',
            'messages' => [
                ['role' => 'system', 'content' => $master_prompt],
                ['role' => 'user', 'content' => $chunk]
            ],
            'max_tokens' => $max_tokens,
            'temperature' => 0.7,
        );

        $options = array(
            'http' => array(
                'header' => "Content-Type: application/json\r\n" .
                            "Authorization: Bearer $api_key\r\n",
                'method' => 'POST',
                'content' => json_encode($data),
                'ignore_errors' => true
            ),
        );

        $context = stream_context_create($options);
        $result = file_get_contents($endpoint, false, $context);

        if ($result === FALSE) {
            $error = error_get_last()['message'];
            return 'Error calling OpenAI API: ' . $error;
        }

        $response = json_decode($result, true);
        if (isset($response['choices'][0]['message']['content'])) {
            $responses[] = $response['choices'][0]['message']['content'];
        } else {
            return 'Unexpected API response format: ' . print_r($response, true);
        }
    }

    // Combine the responses
    return implode("\n\n", $responses);
}

function form_submission_and_openai_shortcode() {
    ob_start();

    // Initialize variables
    $job_description = '';
    $openai_response = '';

    // Check if the form was submitted
    if (isset($_POST['submit_job_description'])) {
        // Sanitize and process the submitted job description
        $job_description = sanitize_textarea_field($_POST['job_description']);
        $openai_response = handle_openai_api_request($job_description);
    }

    // Function to handle the download request
    function download_openai_response() {
        if (isset($_POST['download_response']) && !empty($_POST['response_data'])) {
            $response_data = sanitize_textarea_field($_POST['response_data']);
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="generated_copy.txt"');
            echo $response_data;
            exit;
        }
    }

    // Call the function if the download button is pressed
    download_openai_response();

    // Display the form and output editor
    ?>
    <style>
        .form-container {
            margin: 20px;
            text-align: center; /* Center content */
        }

        .form-container textarea {
            width: 100%;
            padding: 10px;
            font-size: 16px;
            border: 1px solid #ccc;
            border-radius: 4px;
            box-sizing: border-box;
        }

        .form-container textarea#job_description {
            height: 200px;
            resize: vertical;
        }

        .form-container textarea#openai_response {
            height: 300px;
            resize: vertical;
        }

        .form-container button {
            background-color: #0073aa;
            color: #fff;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            font-size: 16px;
            cursor: pointer;
            transition: background-color 0.3s ease;
            margin-top: 10px;
            display: inline-block; /* Ensure button is treated as inline element for centering */
        }

        .form-container button:hover {
            background-color: #005177;
        }

        .openai-response {
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
            background-color: #f9f9f9;
            margin-top: 20px;
        }
    </style>

    <div class="form-container">
        <form method="post" action="">
            <textarea id="job_description" name="job_description" placeholder="Paste your job description here" required><?php echo esc_textarea($job_description); ?></textarea>
            <button type="submit" name="submit_job_description">Generate Copy</button>
        </form>

        <?php if (!empty($openai_response)): ?>
            <div class="openai-response">
                <textarea id="openai_response"><?php echo esc_textarea($openai_response); ?></textarea>
                <form method="post" action="">
                    <input type="hidden" name="response_data" value="<?php echo esc_attr($openai_response); ?>">
                    <button type="submit" name="download_response">Download Response</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('form_submission_and_openai', 'form_submission_and_openai_shortcode');
