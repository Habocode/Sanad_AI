import pandas as pd
from sklearn.model_selection import train_test_split

# Load dataset
df = pd.read_csv("conversations_training.csv")

print("Original rows:", len(df))

# Remove rows with missing input/output
df = df.dropna(subset=["input", "output"])

# Remove exact duplicate input-output pairs
df = df.drop_duplicates(subset=["input", "output"])

# Remove duplicate inputs
df = df.drop_duplicates(subset=["input"])

print("After removing duplicates:", len(df))

# 80% train, 20% temporary
train_df, temp_df = train_test_split(
    df,
    test_size=0.20,
    random_state=42,
    shuffle=True
)

# 10% validation, 10% test
val_df, test_df = train_test_split(
    temp_df,
    test_size=0.50,
    random_state=42,
    shuffle=True
)

# Save
train_df.to_csv("train.csv", index=False)
val_df.to_csv("validation.csv", index=False)
test_df.to_csv("test.csv", index=False)

print("\nFinal split:")
print("Train:", len(train_df))
print("Validation:", len(val_df))
print("Test:", len(test_df))

#checking for leakage

train = pd.read_csv("train.csv")
val = pd.read_csv("validation.csv")
test = pd.read_csv("test.csv")

train_inputs = set(train["input"].astype(str))
val_inputs = set(val["input"].astype(str))
test_inputs = set(test["input"].astype(str))

print("Train–Validation overlap:",
      len(train_inputs & val_inputs))

print("Train–Test overlap:",
      len(train_inputs & test_inputs))

print("Validation–Test overlap:",
      len(val_inputs & test_inputs))