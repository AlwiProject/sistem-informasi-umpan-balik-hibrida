import pandas as pd
from sklearn.feature_extraction.text import TfidfVectorizer
from sklearn.naive_bayes import MultinomialNB
from sklearn.model_selection import train_test_split
from sklearn.metrics import accuracy_score, confusion_matrix, classification_report
import matplotlib.pyplot as plt
import seaborn as sns
import pickle

# 1. Load Dataset
print("Memuat dataset...")
# Ganti 'train_preprocess.tsv' dengan nama file Anda yang sebenarnya jika berbeda
df = pd.read_csv('train_preprocess_ori.tsv', sep='\t') 

# Penanganan jika file tidak memiliki baris judul (header) secara default
if 'text' not in df.columns or 'sentiment' not in df.columns:
    df = pd.read_csv('train_preprocess.tsv', sep='\t', header=None, names=['text', 'sentiment'])

df = df.dropna() # Buang data yang kosong

# 2. Membagi Data (80% untuk Belajar, 20% untuk Ujian/Testing)
X = df['text']
y = df['sentiment']
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2, random_state=42)

# 3. Ekstraksi Fitur Teks (Mengubah kata-kata menjadi angka/vektor TF-IDF)
print("Mengekstraksi fitur teks...")
vectorizer = TfidfVectorizer(max_features=5000) 
X_train_vec = vectorizer.fit_transform(X_train)
X_test_vec = vectorizer.transform(X_test)

# 4. Melatih Model (Naive Bayes)
print("Melatih model Naive Bayes...")
model = MultinomialNB()
model.fit(X_train_vec, y_train)

# 5. Evaluasi & Pembuatan Confusion Matrix
print("\nMengevaluasi model pada data uji (testing)...")
y_pred = model.predict(X_test_vec)

akurasi = accuracy_score(y_test, y_pred)
print(f"AKURASI MODEL: {akurasi * 100:.2f}%")
print("\nLaporan Detail:\n", classification_report(y_test, y_pred))

# Membuat Gambar Confusion Matrix
cm = confusion_matrix(y_test, y_pred)
plt.figure(figsize=(8, 6))
labels = sorted(y.unique()) 
sns.heatmap(cm, annot=True, fmt='d', cmap='Blues', xticklabels=labels, yticklabels=labels)
plt.title('Confusion Matrix - Klasifikasi Sentimen Naive Bayes')
plt.xlabel('Tebakan Sistem (Predicted)')
plt.ylabel('Kenyataan (Actual)')

# Simpan sebagai gambar untuk dimasukkan ke jurnal
plt.savefig('confusion_matrix.png', dpi=300, bbox_inches='tight')
print("\n=> Gambar 'confusion_matrix.png' berhasil dibuat untuk jurnal Anda!")

# 6. Menyimpan Model ke file .pkl
with open('model.pkl', 'wb') as f:
    pickle.dump(model, f)
with open('vectorizer.pkl', 'wb') as f:
    pickle.dump(vectorizer, f)

print("=> SUKSES: model.pkl dan vectorizer.pkl berhasil diperbarui!")