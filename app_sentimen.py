from flask import Flask, request, jsonify
import pickle
import os

app = Flask(__name__)

# Load model & vectorizer
model = None
vectorizer = None

if os.path.exists('model.pkl') and os.path.exists('vectorizer.pkl'):
    with open('model.pkl', 'rb') as f:
        model = pickle.load(f)
    with open('vectorizer.pkl', 'rb') as f:
        vectorizer = pickle.load(f)

@app.route('/predict', methods=['POST'])
def predict():
    global model, vectorizer
    data = request.get_json()
    teks = data.get('teks', '')
    teks_lower = teks.lower()

    if not teks:
        return jsonify({'error': 'Teks kosong'}), 400

    # 1. PENDEKATAN RULE-BASED NEGATIF (Untuk menangani kritikan bersayap/sindiran)
    kata_kunci_negatif = ['mohon agak', 'kurang', 'seharusnya', 'lebih baik', 'tolong diperbaiki', 'tolong lebih', 'jangan', 'lama', 'lambat', 'jutek']
    
    if any(keyword in teks_lower for keyword in kata_kunci_negatif):
        return jsonify({
            'status': 'success',
            'label_sentimen': 'Negatif',
            'skor_sentimen': 0.85 # Skor confidence buatan untuk rule-based
        })

    # 2. PENDEKATAN RULE-BASED POSITIF (Untuk menangani pujian yang gagal dideteksi model)
    kata_kunci_positif = ['sangat baik', 'bagus', 'ramah', 'murah senyum', 'keren', 'mantap', 'terima kasih', 'cepat', 'sangat membantu', 'puas']
    
    if any(keyword in teks_lower for keyword in kata_kunci_positif):
        return jsonify({
            'status': 'success',
            'label_sentimen': 'Positif',
            'skor_sentimen': 0.85
        })

    # 3. PENDEKATAN MACHINE LEARNING (Jika tidak terdeteksi oleh aturan di atas)
    if model and vectorizer:
        X_vec = vectorizer.transform([teks])
        pred = model.predict(X_vec)[0]
        probs = model.predict_proba(X_vec)
        conf = float(max(probs[0]))
    else:
        pred = 'Netral'
        conf = 0.50

    # Penyesuaian output bahasa inggris dari dataset IndoNLU ke bahasa Indonesia
    if pred == 'positive': pred = 'Positif'
    elif pred == 'negative': pred = 'Negatif'
    elif pred == 'neutral': pred = 'Netral'

    return jsonify({
        'status': 'success',
        'label_sentimen': pred,
        'skor_sentimen': round(conf, 2)
    })

if __name__ == '__main__':
    app.run(port=5000, debug=True)