<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => ['required', 'string', 'max:5000'],
            'history' => ['nullable', 'array'],
        ]);

        // ✅ الأفضل: ضع المفتاح في .env
        $apiKey = config('services.gemini.key');
        // أو مؤقتاً:
        // $apiKey = 'AIzaSy...'; // المفتاح الصحيح من AI Studio

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Gemini API key is not configured.'
            ], 500);
        }

        $contents = [];

        foreach ($request->input('history', []) as $message) {
            if (
                !isset($message['role'], $message['text']) ||
                !in_array($message['role'], ['user', 'model'])
            ) {
                continue;
            }

            $contents[] = [
                'role' => $message['role'],
                'parts' => [['text' => $message['text']]]
            ];
        }

        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $request->message]]
        ];

        // ✅ استخدم v1beta مع موديل صحيح و systemInstruction
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $apiKey;

        $response = Http::timeout(60)
            ->withHeaders(['Content-Type' => 'application/json'])
            ->post($url, [
                'systemInstruction' => [
                    'parts' => [
                        ['text' => $this->systemPrompt()]
                    ]
                ],
                'contents' => $contents,
                'generationConfig' => [
                    'temperature' => 0.7,
                    'maxOutputTokens' => 500,
                ],
            ]);

            dd($response->json());
        // ✅ سجّل الخطأ الحقيقي للتصحيح
        if (!$response->successful()) {
            Log::error('Gemini API Error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ أثناء الاتصال بمساعد سَنَد.',
                'debug' => app()->environment('local') ? $response->json() : null,
            ], 500);
        }

        $text = data_get(
            $response->json(),
            'candidates.0.content.parts.0.text'
        );

        if (!$text) {
            Log::warning('Gemini Empty Response', $response->json());
            return response()->json([
                'success' => false,
                'message' => 'لم يتم الحصول على رد من Gemini.'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' => $text,
        ]);
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
أنت "سَنَد"، مساعد دعم نفسي رقمي آمن ومتعاطف.
تحدث باللغة العربية، ويفضل استخدام اللهجة الليبية بشكل طبيعي عندما يكون ذلك مناسباً.
مهمتك:
- الاستماع للمستخدم باهتمام.
- الرد بطريقة هادئة وإنسانية وغير حُكمية.
- مساعدة المستخدم على فهم مشاعره.
- تقديم اقتراحات بسيطة وآمنة.
- إذا كان المستخدم يريد فقط التحدث، استمع إليه ولا تحول كل شيء إلى نصائح.
- لا تدّعي أنك طبيب أو معالج نفسي.
- لا تقدم تشخيصاً طبياً.
- لا تصف أدوية.
إذا ظهرت مؤشرات على خطر مباشر أو إيذاء النفس، شجع المستخدم على التواصل فوراً مع شخص موثوق أو خدمات الطوارئ.
اجعل إجاباتك قصيرة إلى متوسطة، واضحة، ودافئة.
PROMPT;
    }
}