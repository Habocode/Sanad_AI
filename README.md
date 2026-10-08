# Sanad_AI

An AI assistant that helps with therapy-related problems, with a Libyan Arabic (Libyan dialect) conversational style.

## Project Structure

```
Sanad_AI/
├── data/
│   ├── raw/          # Original, unmodified source data
│   └── processed/    # Cleaned data split into train and validation sets
├── fastapi/          # FastAPI app that serves the model over HTTP
├── src/              # Multi-tool agent code
└── README.md
```


- **`data/raw/`**: Original data as collected. Not modified.
- **`data/processed/`**: Cleaned and split data, with `train` and `validation` files used for training and evaluation.
- **`fastapi/`**: The API server that loads the model and exposes the `/api/chat` endpoint.
- **`src/`**: The multi-tool agent logic.

## Setup

### Requirements

- Python 3.10 or newer
- Git (optional, for cloning)
- Roughly 8 GB of free RAM for the 2B-parameter model on CPU

### Installation

```bash
git clone https://github.com/YOUR_USERNAME/Sanad_AI.git
cd Sanad_AI

python -m venv venv
source venv/bin/activate        # Windows: venv\Scripts\activate

pip install torch --index-url https://download.pytorch.org/whl/cpu
pip install -r fastapi/requirements.txt
```

## Running the API

1. Open `fastapi/main.py` and replace `YOUR_HF_USERNAME` in `model_id` with your Hugging Face repo ID.
2. Start the server:

```bash
cd fastapi
uvicorn main:app --host 127.0.0.1 --port 8000
```

3. Open the interactive docs at http://127.0.0.1:8000/docs, or send a request:

```bash
curl -X POST http://127.0.0.1:8000/api/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "مرحبا"}'
```

The first run downloads the model from Hugging Face, which may take a while. Expect several seconds per reply on CPU.

## Multi-Tool Agent (`src/`)

The agent code in `src/` contains the multi-tool logic. Add usage instructions here, such as how to run it and which tools it provides.

## Data

- **`data/raw/`** holds the original data and should not be edited by hand.
- **`data/processed/`** holds the `train` and `validation` splits used for fine-tuning and evaluation.

## Model

The API uses a Qwen-based model fine-tuned for supportive counseling in Libyan Arabic.

## Notes

- Replies are generated on CPU in float32, so the machine needs roughly 8 GB of free RAM.
- This project is an experimental assistant and is **not a substitute for professional mental health care**. If you or someone you know is in crisis, contact local emergency services or a qualified professional.