# Multi-Tool AI Support Agent

## Overview

This component implements a **single model-agnostic AI agent with multiple tools**.

The implementation is intentionally independent of any specific language model,
frontend framework, backend framework, image-generation provider, or web-search provider.

The main execution flow is:

```text
User Message
    |
    v
Mandatory Safety Guard
    |
    v
Language Model Router
    |
    +---------------------+----------------------+
    |                     |                      |
    v                     v                      v
Normal Reply       Visual Exercise Tool   Provider Search Tool
    |                     |                      |
    +---------------------+----------------------+
                          |
                          v
                 Structured JSON Result
                          |
                          v
                     Frontend
```

The core objective is to keep every integration point replaceable while preserving
one stable contract between the model, agent, backend, and frontend.

---

## 1. Main Components

### 1.1 Mandatory Safety Guard

The Safety Guard runs **before any model-selected tool is executed**.

Its role is routing and protection, not diagnosis.

The current prototype checks for:
- possible urgent self-harm language;
- requests for diagnosis;
- requests for medication or dosage.

It returns a stable object:

```json
{
  "level": "routine",
  "allow_tools": true,
  "message": "Normal non-diagnostic support flow is allowed."
}
```

If `allow_tools` is `false`, normal tool execution stops.

The current implementation is intentionally simple and can later be replaced by a
stronger classifier or safety service without changing the rest of the architecture,
as long as the same output contract is preserved.

---

### 1.2 Visual Exercise Tool

The Visual Exercise Tool creates a visual aid for a predefined wellbeing exercise.

The language model does **not** send arbitrary exercise instructions directly to the
image generator. Instead, it selects an exercise from an approved allow-list.

Current examples include:

```text
box_breathing
grounding_54321
```

This design separates:
- model reasoning;
- approved exercise content;
- visual generation.

The tool supports two modes.

#### MOCK mode

Creates a local placeholder image so the integration can be tested without an API.

```python
generate_exercise_visual(
    exercise_name="box_breathing",
    mode="mock"
)
```

#### LIVE mode

Calls an image-generation provider.

The current implementation uses Hugging Face Inference Providers as an example, but
the function can be replaced with another image API without changing the orchestrator.

The frontend should render Arabic labels and exercise steps separately from the
generated image rather than depending on the image model to render Arabic text.

---

### 1.3 Professional Support Search Tool

This tool searches public sources using:

```text
support_type
city
country
```

Supported categories currently include:

```text
psychologist
psychiatrist
counselor
mental_health_clinic
```

Example call:

```python
find_professional_support(
    support_type="psychologist",
    city="Tripoli",
    country="Libya",
    mode="mock"
)
```

In MOCK mode, all returned providers are fictional.

In LIVE mode, the current example uses Tavily web search.

Search results are treated as **candidates**, not verified medical recommendations.
A public search result alone does not verify licensing, qualifications, availability,
or clinical suitability.

A production implementation should preferably use a trusted directory or add a
separate verification layer.

---

## 2. Model-Independent Routing

The agent is not tied to Qwen, Gemini, OpenAI, Claude, Llama, Mistral, or another
specific model.

Any model can be used if its output is converted to the following JSON structure:

```json
{
  "response": "Short user-facing response",
  "tool": "none",
  "tool_input": {
    "exercise_name": null,
    "support_type": null,
    "city": null,
    "country": "Libya"
  }
}
```

Allowed values for `tool` are:

```text
none
visual_exercise
provider_search
```

### Visual exercise example

```json
{
  "response": "A short breathing exercise may be useful.",
  "tool": "visual_exercise",
  "tool_input": {
    "exercise_name": "box_breathing",
    "support_type": null,
    "city": null,
    "country": "Libya"
  }
}
```

### Provider search example

```json
{
  "response": "Professional support options can be searched in the requested city.",
  "tool": "provider_search",
  "tool_input": {
    "exercise_name": null,
    "support_type": "psychologist",
    "city": "Tripoli",
    "country": "Libya"
  }
}
```

---

## 3. Connecting Any Language Model

The only required integration point is a callable with this signature:

```python
def model_callable(system_prompt: str, user_text: str) -> str:
    return raw_model_output
```

The returned string must contain the routing JSON.

The agent is then created with:

```python
agent = MultiToolSupportAgent(
    model_callable=model_callable,
    visual_mode="mock",
    search_mode="mock"
)
```

### Example: API-based model adapter

```python
import requests
import json

def model_callable(system_prompt: str, user_text: str) -> str:
    response = requests.post(
        "https://MODEL-BACKEND/example",
        json={
            "system_prompt": system_prompt,
            "user_text": user_text
        },
        timeout=30
    )

    response.raise_for_status()
    result = response.json()

    return json.dumps(result["model_output"], ensure_ascii=False)
```

### Example: local model

```python
def model_callable(system_prompt: str, user_text: str) -> str:
    generated_text = ...
    return generated_text
```

No other agent code needs to change.

---

## 4. Multi-Tool Orchestrator

`MultiToolSupportAgent` is the main class called by the backend.

The sequence is:

```text
1. Receive user text
2. Run Safety Guard
3. Stop if normal tools are not allowed
4. Ask the language model to select an action
5. Validate the routing JSON
6. Execute the selected tool
7. Return one structured object
```

Example:

```python
result = agent.run(
    "I would like a breathing exercise."
)
```

Possible result:

```json
{
  "status": "ok",
  "response": "A short breathing exercise may be useful.",
  "tool": "visual_exercise",
  "tool_input": {
    "exercise_name": "box_breathing"
  },
  "tool_result": {
    "status": "ok",
    "exercise_name": "box_breathing",
    "image_path": "/path/to/image.png"
  }
}
```

---

## 5. Frontend Integration

The frontend should **not call individual tools directly**.

The recommended flow is:

```text
Frontend
    |
    | POST user message
    v
Backend
    |
    | agent.run(message)
    v
Multi-Tool Agent
    |
    v
Structured JSON
    |
    v
Frontend Rendering
```

A single backend endpoint is enough:

```http
POST /api/support
```

Request:

```json
{
  "message": "User message"
}
```

The frontend should interpret the response as follows:

- `tool == "none"`: display `response`.
- `tool == "visual_exercise"`: display `response`, exercise steps, and the returned image.
- `tool == "provider_search"`: display candidates, source URLs, and verification status.
- `tool_result.status == "needs_input"`: ask for the missing field such as city.
- `status == "safety_stop"`: activate the dedicated safety flow.

---

## 6. FastAPI Backend Example

```python
from fastapi import FastAPI
from pydantic import BaseModel

app = FastAPI()

class SupportRequest(BaseModel):
    message: str

@app.post("/api/support")
def support(request: SupportRequest):
    return agent.run(request.message)
```

---

## 7. Laravel Integration

Laravel can remain the main application backend while the Python agent runs as a separate AI service.

```text
Frontend
   |
   v
Laravel
   |
   | HTTP
   v
Python Agent Service
   |
   +-- Safety Guard
   +-- Model Router
   +-- Visual Tool
   +-- Provider Search
   |
   v
Laravel
   |
   v
Frontend
```

### Laravel Controller Example

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AgentController extends Controller
{
    public function chat(Request $request)
    {
        $request->validate([
            'message' => 'required|string'
        ]);

        $response = Http::timeout(60)->post(
            config('services.ai_agent.url') . '/api/support',
            [
                'message' => $request->message
            ]
        );

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'AI service is currently unavailable.'
            ], 503);
        }

        return response()->json($response->json());
    }
}
```

Laravel route:

```php
use App\Http\Controllers\AgentController;

Route::post('/agent/chat', [AgentController::class, 'chat']);
```

Environment:

```env
AI_AGENT_URL=http://127.0.0.1:8000
```

`config/services.php`:

```php
'ai_agent' => [
    'url' => env('AI_AGENT_URL', 'http://127.0.0.1:8000'),
],
```

This keeps authentication, users, conversations, database logic, and application APIs
inside Laravel while AI-specific functionality stays in Python.

---

## 8. Switching External Tools to LIVE Mode

### Image generation

```python
os.environ["HF_TOKEN"] = "..."
os.environ["IMAGE_MODEL"] = "black-forest-labs/FLUX.1-schnell"

agent.visual_mode = "live"
```

### Public web search

```python
os.environ["TAVILY_API_KEY"] = "..."

agent.search_mode = "live"
```

API keys should never be committed to GitHub.

---

## 9. Stable Contracts

### Model → Agent

```json
{
  "response": "text",
  "tool": "none | visual_exercise | provider_search",
  "tool_input": {
    "exercise_name": null,
    "support_type": null,
    "city": null,
    "country": "Libya"
  }
}
```

### Frontend → Backend

```json
{
  "message": "user message"
}
```

### Backend → Frontend

Return:

```python
agent.run(user_message)
```

The frontend does not need to know which LLM, image provider, or search provider is being used.

---

## 10. Repository Structure

```text
project/
├── Multi_Tool_AI_Agent.ipynb
├── AGENT_EXPLANATION.md
└── requirements.txt
```

For deployment, the notebook can later be split into:

```text
ai-service/
├── main.py
├── agent.py
├── safety.py
├── tools/
│   ├── visual.py
│   └── provider_search.py
└── requirements.txt
```

---

## 11. Safety and Scope Notes

- The system is not a diagnostic tool.
- It must not prescribe medication or dosage.
- Exercise generation is restricted to an approved allow-list.
- Provider search results should expose source URLs.
- Public search results should not automatically be described as verified professionals.
- High-risk messages must bypass normal tool execution and use a dedicated safety flow.
- Sensitive user data should not be unnecessarily included in external search queries.
