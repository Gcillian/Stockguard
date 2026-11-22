<?php
return [
    'confidence_threshold' => (float) env('YOLO_CONFIDENCE_THRESHOLD', .8),
    'yolo_service_url' => env('YOLO_SERVICE_URL', ''),
    'yolo_service_token' => env('YOLO_SERVICE_TOKEN', ''),
    'demo_mode' => in_array(env('APP_ENV', 'production'), ['local', 'testing'], true) && (bool) env('YOLO_DEMO_MODE', false),
];
