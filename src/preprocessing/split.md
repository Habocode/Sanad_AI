# Dataset Splitting and Leakage Check

The `conversations_training.csv` dataset was first cleaned by removing duplicate records and checking for repeated inputs. The cleaned dataset was then divided into three subsets:

* **Training set:** 80%
* **Validation set:** 10%
* **Test set:** 10%

The training set is used for model fine-tuning, the validation set is used to monitor performance during training, and the test set is reserved for final evaluation.

## Leakage Check

After splitting the cleaned dataset, the inputs in each subset were compared to check for overlap between the training, validation, and test sets.

The final leakage check showed **no overlapping inputs between any of the three splits**. This confirms that the same input does not appear across different subsets and helps ensure that the evaluation results are not affected by data leakage.
Final split:
Train: 15651
Validation: 1956
Test: 1957
Train–Validation overlap: 0
Train–Test overlap: 0
Validation–Test overlap: 0
