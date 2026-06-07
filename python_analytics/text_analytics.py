import re
from collections import Counter

MALAY_STOPWORDS = {
    "ada", "adalah", "agar", "akan", "aku", "amat", "anda", "antara", "atau",
    "bagi", "bagi", "bahawa", "banyak", "baru", "beberapa", "beliau", "bila",
    "boleh", "dan", "dari", "dengan", "di", "dia", "hal", "hanya", "ia",
    "ialah", "ini", "itu", "jadi", "jika", "juga", "kami", "kata", "ke",
    "kerana", "kita", "lagi", "lah", "lebih", "macam", "mana", "mereka",
    "ni", "no", "nya", "oleh", "pada", "pun", "saya", "sebagai", "sebuah",
    "secara", "sedang", "sehingga", "seperti", "serta", "setiap", "sudah",
    "sangat", "tapi", "telah", "tentang", "terhadap", "tidak", "tu", "untuk",
    "yang", "yaitu",
}

_TOKEN_RE = re.compile(r"[a-zA-Z']+")


def _load_english_stopwords() -> set[str]:
    try:
        from nltk.corpus import stopwords

        return set(stopwords.words("english"))
    except LookupError:
        import nltk

        nltk.download("stopwords", quiet=True)
        from nltk.corpus import stopwords

        return set(stopwords.words("english"))


def get_stopwords() -> set[str]:
    return _load_english_stopwords() | MALAY_STOPWORDS


def tokenize_feedback(texts: list[str]) -> Counter:
    stop = get_stopwords()
    counter: Counter = Counter()

    for text in texts:
        if not text or not str(text).strip():
            continue
        for word in _TOKEN_RE.findall(str(text).lower()):
            if len(word) >= 2 and word not in stop:
                counter[word] += 1

    return counter


def tokenize_products(texts: list[str]) -> Counter:
    stop = get_stopwords()
    counter: Counter = Counter()

    for text in texts:
        if not text or not str(text).strip():
            continue
        for item in str(text).split(","):
            phrase = item.strip()
            if not phrase:
                continue
            counter[phrase.lower()] += 1
            for word in _TOKEN_RE.findall(phrase.lower()):
                if len(word) >= 2 and word not in stop:
                    counter[word] += 1

    return counter


def counter_to_terms(counter: Counter, max_terms: int = 100) -> list[dict]:
    return [{"text": term, "weight": weight} for term, weight in counter.most_common(max_terms)]
