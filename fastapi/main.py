from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
import torch
from transformers import AutoTokenizer, AutoModelForCausalLM

app = FastAPI()

print("Loading model onto CPU (this may take a minute)...")
# Replace with your actual Hugging Face repo ID
model_id = "devmousa/qwen3.5-2b-libyan-counselor_continued"

tokenizer = AutoTokenizer.from_pretrained(model_id)

# Load explicitly on CPU using float32
model = AutoModelForCausalLM.from_pretrained(
    model_id,
    torch_dtype=torch.float32,  # Standard 32-bit floats for CPU
    device_map="cpu"            # Explicitly target CPU
)
model.eval()
print("Model loaded and ready on CPU!")

SYSTEM_PROMPT = "أنت مجيب نفسي وداعم عاطفي تتكلم باللهجة الليبية. تقديمك للمساعدة بيكون بأسلوب هادي وداعم."


class ChatRequest(BaseModel):
    message: str


# Plain "def" (not "async def") so FastAPI runs this in a thread pool
# and the server stays responsive while the model generates.
@app.post("/api/chat")
def chat(request: ChatRequest):
    try:
        messages = [
            {"role": "system", "content": SYSTEM_PROMPT},
            {"role": "user", "content": request.message},
        ]

        prompt = tokenizer.apply_chat_template(
            messages,
            tokenize=False,
            add_generation_prompt=True,
            enable_thinking=False,  # Qwen3 family: skip the reasoning block
        )
        inputs = tokenizer(prompt, return_tensors="pt").to("cpu")

        with torch.no_grad():
            outputs = model.generate(
                **inputs,
                max_new_tokens=100,   # Kept short for faster CPU testing
                do_sample=True,       # Required for temperature to take effect
                temperature=0.6,
                pad_token_id=tokenizer.eos_token_id,
            )

        input_length = inputs.input_ids.shape[1]
        reply = tokenizer.decode(outputs[0][input_length:], skip_special_tokens=True)

        return {"reply": reply.strip()}

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))