<?php
/**
 * includes/dempster_shafer.php
 *
 * Implementasi Dempster's Rule of Combination yang benar
 * untuk Sistem Pakar berbasis Dempster-Shafer Theory (DST).
 *
 * ─── KONSEP UTAMA ────────────────────────────────────────────────────────────
 *
 * Frame of Discernment (Θ): himpunan semua hipotesis yang mungkin
 *   Dalam konteks ini = himpunan semua penyakit yang ada di sistem.
 *
 * Mass Function (m): fungsi keyakinan atas setiap subset dari Θ
 *   m(A)   = massa / belief terhadap himpunan hipotesis A
 *   m(Θ)   = massa untuk ketidakpastian total (frame penuh)
 *   Syarat: Σ m(A) = 1 untuk semua A ⊆ Θ
 *
 * Representasi Key Himpunan:
 *   - Satu penyakit : "P01"
 *   - Banyak penyakit : "P01|P02|P04" (diurutkan, dipisah "|")
 *   - Frame of discernment (Θ) : "THETA"
 *   - Konflik (∅) : string kosong "" → tidak pernah disimpan ke array
 *
 * ─── DEMPSTER'S RULE OF COMBINATION ─────────────────────────────────────────
 *
 *   Diberikan dua mass function m1 dan m2:
 *
 *   (1) Hitung semua kombinasi pasangan (B, C):
 *       - Jika B ∩ C = ∅  → tambahkan m1(B)·m2(C) ke K (konflik)
 *       - Jika B ∩ C ≠ ∅  → tambahkan m1(B)·m2(C) ke combined[B∩C]
 *
 *   (2) Normalisasi (hapus konflik):
 *       m12(A) = combined[A] / (1 − K)
 *
 * ─────────────────────────────────────────────────────────────────────────────
 */


/**
 * Menghitung irisan (intersection) antara dua himpunan.
 *
 * Aturan:
 *   THETA ∩ X = X        (Θ adalah frame penuh, irisan dengan apapun = apapun)
 *   X ∩ THETA = X
 *   {P01} ∩ {P02} = ∅    (berbeda → konflik, kembalikan "")
 *   {P01,P02} ∩ {P01} = {P01}
 *
 * @param  string $setA Key himpunan A (contoh: "P01|P02" atau "THETA")
 * @param  string $setB Key himpunan B
 * @return string       Key irisan, atau "" jika himpunan kosong (konflik ∅)
 */
function intersectSets(string $setA, string $setB): string
{
    // THETA ∩ X = X  (frame of discernment)
    if ($setA === 'THETA') return $setB;
    if ($setB === 'THETA') return $setA;

    // Pecah menjadi array kode penyakit
    $arrA = explode('|', $setA);
    $arrB = explode('|', $setB);

    // Irisan dua himpunan
    $intersection = array_values(array_intersect($arrA, $arrB));

    if (empty($intersection)) {
        return ''; // Irisan kosong = konflik (∅)
    }

    // Kembalikan sebagai key terurut untuk konsistensi
    sort($intersection);
    return implode('|', $intersection);
}


/**
 * Mengombinasikan dua mass function menggunakan Dempster's Rule of Combination.
 *
 * Langkah:
 *   1. Iterasi semua pasangan (B ∈ m1) × (C ∈ m2)
 *   2. Hitung produk massanya: m1(B) · m2(C)
 *   3. Hitung irisan B ∩ C:
 *      - Jika ∅ → akumulasikan ke K (konflik)
 *      - Jika ≠ ∅ → akumulasikan ke combined[B∩C]
 *   4. Normalisasi: m12(A) = combined[A] / (1 − K)
 *
 * @param  array $m1 Mass function pertama  [ "P01" => 0.8, "THETA" => 0.2 ]
 * @param  array $m2 Mass function kedua    [ "P02" => 0.5, "THETA" => 0.5 ]
 * @return array     Mass function gabungan (sudah dinormalisasi)
 */
function kombinasiDS(array $m1, array $m2): array
{
    $combined = []; // Akumulasi sebelum normalisasi
    $K        = 0.0; // Total konflik

    // ─── Langkah 1 & 2 & 3: Iterasi semua kombinasi pasangan ──────────────
    foreach ($m1 as $setB => $massB) {
        foreach ($m2 as $setC => $massC) {
            $product      = $massB * $massC;
            $intersection = intersectSets((string) $setB, (string) $setC);

            if ($intersection === '') {
                // Irisan kosong → konflik
                $K += $product;
            } else {
                // Akumulasi massa ke himpunan hasil irisan
                $combined[$intersection] = ($combined[$intersection] ?? 0.0) + $product;
            }
        }
    }

    // ─── Langkah 4: Normalisasi ───────────────────────────────────────────
    // Jika konflik total mendekati 1, kombinasi tidak bisa dilakukan
    if (abs(1.0 - $K) < 1e-10) {
        // Fallback: distribusi uniform ke Theta (completely contradictory sources)
        return ['THETA' => 1.0];
    }

    $normFactor = 1.0 - $K;
    $result     = [];
    foreach ($combined as $set => $mass) {
        $normalized = $mass / $normFactor;
        if ($normalized > 1e-10) { // Buang nilai yang sangat kecil
            $result[$set] = $normalized;
        }
    }

    return $result;
}


/**
 * Membangun mass function awal dari satu evidence (bukti).
 *
 * Satu evidence menghasilkan dua entri:
 *   m({penyakit_1|penyakit_2|...}) = belief
 *   m(THETA)                        = 1 - belief  (ketidakpastian)
 *
 * @param  array $kode_penyakit_list Array kode penyakit yang didukung evidence ini
 * @param  float $belief             Nilai belief pakar (0.0 – 1.0)
 * @return array Mass function dalam format [ "key" => massa ]
 */
function buildMassFunction(array $kode_penyakit_list, float $belief): array
{
    // Pastikan terurut untuk konsistensi key
    sort($kode_penyakit_list);
    $setKey = implode('|', $kode_penyakit_list);

    return [
        $setKey  => (float) $belief,
        'THETA'  => (float) (1.0 - $belief),
    ];
}


/**
 * Menghitung total massa dari sebuah mass function (harus mendekati 1.0).
 * Berguna untuk debugging dan validasi.
 *
 * @param  array $massFunction
 * @return float Total massa (idealnya = 1.0)
 */
function totalMass(array $massFunction): float
{
    return array_sum($massFunction);
}
