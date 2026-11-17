<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class UdiseController extends Controller
{
    public function fetch($code)
    {
        // Mocking a fetch from the UDISE+ Portal or Third-Party API
        // In production, you would use Http::get() or a web scraper here.

        // Validate 11-digit UDISE format loosely for the mock
        if (strlen($code) !== 11) {
            return response()->json(['message' => 'Invalid UDISE Code format. Must be 11 digits.'], 400);
        }

        // Simulating network delay
        sleep(1);

        // Mock response data
        $mockData = [
            'udise_code' => $code,
            'school_name' => 'GOVT. SEC. SCHOOL ' . strtoupper(substr(md5($code), 0, 4)),
            'board_type' => 'STATE_BOARD',
            'state' => 'RAJASTHAN',
            'district' => 'JAIPUR',
            'address' => 'Ward ' . rand(1, 20) . ', Main Block',
        ];

        return response()->json($mockData);
    }
}
