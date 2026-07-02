<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// All endpoints live under /api/v1 — see routes/api_v1.php and
// docs/API_AUTH.md. Introduce routes/api_v2.php the same way if/when a
// breaking change ever requires a new version.
Route::prefix('v1')->group(base_path('routes/api_v1.php'));
