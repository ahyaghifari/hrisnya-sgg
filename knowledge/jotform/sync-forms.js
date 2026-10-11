// Simpan semua form Jotform: <slug-nama-form>.json + gambar di assets/<slug-nama-form>/.
// Pakai: isi API_KEY di bawah, lalu `node knowledge/jotform/sync-forms.js`   (Node 18+, tanpa dependency)
//   Akun region EU: tambah JOTFORM_API_URL=https://eu-api.jotform.com
// Aman diulang: JSON ditimpa (data terbaru), gambar yang sudah ada dilewati.
import fs from "node:fs";
import path from "node:path";
import { fileURLToPath } from "node:url";

const API_KEY = ""; // jangan hardcode lalu di-commit
const API_URL = process.env.JOTFORM_API_URL ?? "https://api.jotform.com";
const dir = path.dirname(fileURLToPath(import.meta.url));

if (!API_KEY) {
    console.error(
        "Isi API_KEY di sync-forms.js dulu (Jotform > Settings > API).",
    );
    process.exit(1);
}

async function api(endpoint) {
    const res = await fetch(`${API_URL}${endpoint}`, {
        headers: { APIKEY: API_KEY },
    });
    const body = await res.json().catch(() => ({}));
    if (!res.ok)
        throw new Error(
            `${endpoint}: HTTP ${res.status} ${body.message ?? ""}`,
        );
    return body;
}

async function allForms() {
    const forms = [];
    for (let offset = 0; ; offset += 1000) {
        const { content } = await api(
            `/user/forms?limit=1000&offset=${offset}`,
        );
        forms.push(...content);
        if (content.length < 1000)
            return forms.filter((f) => f.status !== "DELETED");
    }
}

const slugify = (s) =>
    s
        .toLowerCase()
        .normalize("NFKD")
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
const used = new Set();

let fail = 0;
for (const form of await allForms()) {
    // Nama dari judul form; kalau kembar/kosong, tambah id supaya tidak saling timpa.
    let name = slugify(form.title) || form.id;
    if (used.has(name)) name = `${name}-${form.id}`;
    used.add(name);

    try {
        const questions = await api(`/form/${form.id}/questions`);
        fs.writeFileSync(
            path.join(dir, `${name}.json`),
            JSON.stringify(questions, null, 4),
        );

        // ponytail: cuma field control_image; gambar di header/HTML teks tidak ikut. Tambah kalau dibutuhkan.
        const urls = [
            ...new Set(
                Object.values(questions.content)
                    .filter((q) => q.type === "control_image" && q.src)
                    .map((q) => q.src),
            ),
        ];
        let ok = 0;
        if (urls.length) {
            const out = path.join(dir, "assets", name);
            fs.mkdirSync(out, { recursive: true });
            for (const url of urls) {
                const dest = path.join(
                    out,
                    decodeURIComponent(url.split("/").pop()),
                ); // nama sama dengan upload asli
                if (fs.existsSync(dest)) continue;
                try {
                    const res = await fetch(new URL(url, API_URL)); // ikut redirect ke files.jotform.com
                    if (!res.ok) throw new Error(`HTTP ${res.status}`);
                    fs.writeFileSync(
                        dest,
                        Buffer.from(await res.arrayBuffer()),
                    );
                    ok++;
                } catch (e) {
                    fail++;
                    console.error(
                        `  GAGAL gambar ${name}/${path.basename(dest)}: ${e.message}`,
                    );
                }
            }
        }
        console.log(
            `${name} (${form.id}): ${Object.keys(questions.content).length} field, ${urls.length} gambar unik, ${ok} diunduh`,
        );
    } catch (e) {
        fail++;
        console.error(`GAGAL form ${name} (${form.id}): ${e.message}`);
    }
}
if (fail) process.exit(1);
