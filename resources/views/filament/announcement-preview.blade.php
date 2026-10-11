<x-announcement-content :title="$title ?: '(Judul belum diisi)'" :body="$body" :by="$by" :at="now()->translatedFormat('j F Y H:i')" />
