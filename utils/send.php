<?php

// This function is used to send JSON responses from the server to the client.
// It also sets the HTTP status code and the response headers.
function send($data, $status = 200) {
    // 1) Check if the headers have already been sent. If not, set the Content-Type header to application/json.
    // This is important because we're sending data in JSON format.
    if (!headers_sent()) {
        header('Content-Type: application/json'); // This tells the client that the response is in JSON format.
    }

    // 2) Set the HTTP status code for the response. The default is 200 (OK), but it can be changed for errors.
    http_response_code($status); // This sets the response status code.

    // 3) Convert the $data array to a JSON string and send it in the response body.
    // JSON_UNESCAPED_SLASHES and JSON_UNESCAPED_UNICODE ensure that special characters are properly encoded and slashes are not escaped.
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // 4) End the script execution after sending the response.
    // This ensures that no further code is executed after the response is sent.
    exit;
}
