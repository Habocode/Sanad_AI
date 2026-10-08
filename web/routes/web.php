<?php

use App\Http\Controllers\GeminiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Http;


Route::get('/', function () {
    return view('pages.main');
});

Route::get('/index.html', function () {
    return view('pages.index');
});

Route::get('/pages/{path}', function ($path) {
    $path = str_replace('.html', '', $path);

    return view('pages.' . $path);
});







Route::post('/sanad/gemini/chat', function (Request $request) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Validate request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'max:5000',
            ],

            'history' => [
                'nullable',
                'array',
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Get API key from .env
        |--------------------------------------------------------------------------
        */

        $apiKey = "";


        if (empty($apiKey)) {

            return response()->json([
                'success' => false,
                'message' => 'GEMINI_API_KEY is missing from the environment.',
            ], 500);
        }


        /*
        |--------------------------------------------------------------------------
        | Current message
        |--------------------------------------------------------------------------
        */

        $message = $validated['message'];


        /*
        |--------------------------------------------------------------------------
        | Conversation history
        |--------------------------------------------------------------------------
        */

        $history = $validated['history'] ?? [];

        $contents = [];


        foreach ($history as $item) {

            if (
                !isset($item['role']) ||
                !isset($item['text'])
            ) {
                continue;
            }


            if (
                !in_array(
                    $item['role'],
                    ['user', 'model'],
                    true
                )
            ) {
                continue;
            }


            $contents[] = [
                'role' => $item['role'],

                'parts' => [
                    [
                        'text' => $item['text'],
                    ],
                ],
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | Add current user message
        |--------------------------------------------------------------------------
        */

        $contents[] = [
            'role' => 'user',

            'parts' => [
                [
                    'text' => $message,
                ],
            ],
        ];


        /*
        |--------------------------------------------------------------------------
        | Gemini endpoint
        |--------------------------------------------------------------------------
        */

       $url =
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';

        /*
        |--------------------------------------------------------------------------
        | Send request
        |--------------------------------------------------------------------------
        */

        $response = Http::timeout(120)
            ->retry(3, 2000)
            ->withHeaders([
                'x-goog-api-key' => "TestApiKey",
                'Content-Type' => 'application/json',
            ])
            ->post(
        'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent',
                [
                    'system_instruction' => [
                        'parts' => [
                            [
                                'text' => '
            أنت سَنَد AI، مساعد دعم نفسي آمن ولطيف باللغة العربية واللهجة الليبية.

            - استمع للمستخدم وتعاطف معه.
            - أجب باختصار ووضوح، عادةً في 2 إلى 4 جمل فقط.
            - لا تكرر كلام المستخدم.
            - لا تشخّص ولا تصف أدوية.
            - لا تدّعي أنك طبيب.
            - قدم نصيحة عملية واحدة أو اثنتين فقط.
            - إذا كان المستخدم في خطر أو يتحدث عن إيذاء نفسه، شجعه فوراً على طلب مساعدة بشرية أو طوارئ.
            - لا تجعل الرد طويلاً أو أكاديمياً.
            ',
                            ],
                        ],
                    ],

                    'contents' => $contents,

                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1000,
                    ],
                ]
            );
    


        /*
        |--------------------------------------------------------------------------
        | Gemini returned an error
        |--------------------------------------------------------------------------
        */

        if (!$response->successful()) {

            $geminiError = $response->json();


            return response()->json([
                'success' => false,

                'message' =>
                    data_get(
                        $geminiError,
                        'error.message',
                        'Gemini API returned an error.'
                    ),

                'status' =>
                    $response->status(),

                'error' =>
                    $geminiError,

            ], 502);
        }


        /*
        |--------------------------------------------------------------------------
        | Parse Gemini response
        |--------------------------------------------------------------------------
        */

        $responseData = $response->json();


        $reply = data_get(
            $responseData,
            'candidates.0.content.parts.0.text'
        );


        /*
        |--------------------------------------------------------------------------
        | Empty response
        |--------------------------------------------------------------------------
        */

        if (empty($reply)) {

            return response()->json([

                'success' => false,

                'message' =>
                    'Gemini returned an empty response.',

                'response' =>
                    $responseData,

            ], 502);
        }


        /*
        |--------------------------------------------------------------------------
        | Success
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => true,

            'message' => $reply,

        ]);


    } catch (\Throwable $e) {

        /*
        |--------------------------------------------------------------------------
        | Laravel exception
        |--------------------------------------------------------------------------
        */

        return response()->json([

            'success' => false,

            'message' => $e->getMessage(),

        ], 500);
    }

})->name('sanad.gemini.chat');
