<?php

$jsonPath = __DIR__.'/api.json';
if (! file_exists($jsonPath)) {
    echo "ERROR: api.json not found\n";
    exit(1);
}

$data = json_decode(file_get_contents($jsonPath), true);
if (! $data) {
    echo "ERROR: Invalid JSON in api.json\n";
    exit(1);
}

$pathsCount = count($data['paths'] ?? []);
$schemasCount = count($data['components']['schemas'] ?? []);

echo "=== OpenAPI 3.1.0 Validation Result ===\n";
echo 'OpenAPI Version: '.($data['openapi'] ?? 'Unknown')."\n";
echo 'Title: '.($data['info']['title'] ?? 'N/A')."\n";
echo 'Total Endpoints (Paths): '.$pathsCount."\n";
echo 'Total Schemas: '.$schemasCount."\n";

if ($pathsCount > 0 && isset($data['openapi']) && str_starts_with($data['openapi'], '3.')) {
    echo "STATUS: VALID OPENAPI SPECIFICATION (PASS)\n";
    exit(0);
} else {
    echo "STATUS: INVALID SPECIFICATION (FAIL)\n";
    exit(1);
}
