<?php

namespace OnePilot\Client\Classes;

class Response
{
    /**
     * @param     $data
     * @param int $status
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public static function make($data, $status = 200)
    {
        $content = array_merge([
            'status' => $status,
        ], $data);

        return response()->json($content, $status);
    }
}
