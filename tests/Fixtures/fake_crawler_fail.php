<?php

// Test double simulating a crawl failure (e.g. Chromium not installed).
fwrite(STDERR, json_encode(['error' => 'chromium missing']));
exit(1);
