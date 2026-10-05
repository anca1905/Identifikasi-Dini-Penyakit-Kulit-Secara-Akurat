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

/**
 * Format angka desimal sesuai standar penulisan ilmiah Indonesia (koma sebagai pemisah desimal).
 */
function formatNumDS(float|int|string $num, int $dec = 4): string
{
    return number_format((float)$num, $dec, ',', '.');
}

/**
 * Memformat representasi string himpunan menjadi notasi matematika.
 * Contoh: "P01|P02" => "{P01, P02}", "THETA" => "{Θ}", "" => "∅"
 */
function formatSetNotation(string $setKey): string
{
    if ($setKey === 'THETA') return '{Θ}';
    if ($setKey === '' || $setKey === 'EMPTY') return '∅';
    $arr = explode('|', $setKey);
    return '{' . implode(', ', $arr) . '}';
}

/**
 * Memformat himpunan lengkap dengan nama penyakitnya.
 */
function formatSetNotationWithNames(string $setKey, array $nama_penyakit_map): string
{
    if ($setKey === 'THETA') return '{Θ} (Ketidakpastian)';
    if ($setKey === '' || $setKey === 'EMPTY') return '∅ (Konflik)';
    
    $arr = explode('|', $setKey);
    $names = [];
    foreach ($arr as $kp) {
        $names[] = isset($nama_penyakit_map[$kp]) ? $nama_penyakit_map[$kp]['nama_penyakit'] : $kp;
    }
    return '{' . implode(', ', $arr) . '} (' . implode(', ', $names) . ')';
}

/**
 * Kombinasi Dempster-Shafer dengan merekam seluruh data detail:
 * matriks perkalian baris x kolom, nilai konflik K, faktor normalisasi (1 - K),
 * dan pemecahan langkah pembagian matematis untuk tiap hipotesis.
 *
 * @param array $m_lama
 * @param array $m_baru
 * @param string $label_lama
 * @param string $label_baru
 * @param string $label_hasil
 * @return array
 */
function kombinasiDSDetail(array $m_lama, array $m_baru, string $label_lama = 'm1', string $label_baru = 'm2', string $label_hasil = 'm3'): array
{
    $matrix = [];
    $conflict_cells = [];
    $K = 0.0;
    $grouped = [];

    foreach ($m_lama as $setR => $valR) {
        $matrix[$setR] = [];
        foreach ($m_baru as $setC => $valC) {
            $product = (float)$valR * (float)$valC;
            $intersection = intersectSets((string)$setR, (string)$setC);
            $matrix[$setR][$setC] = [
                'setR' => (string)$setR,
                'valR' => (float)$valR,
                'setC' => (string)$setC,
                'valC' => (float)$valC,
                'intersection' => $intersection,
                'product' => $product
            ];

            if ($intersection === '') {
                $K += $product;
                $conflict_cells[] = [
                    'setR' => (string)$setR,
                    'setC' => (string)$setC,
                    'valR' => (float)$valR,
                    'valC' => (float)$valC,
                    'product' => $product
                ];
            } else {
                if (!isset($grouped[$intersection])) {
                    $grouped[$intersection] = [];
                }
                $grouped[$intersection][] = $product;
            }
        }
    }

    $one_minus_K = 1.0 - $K;
    $result_mass = [];
    $equations = [];

    if (abs($one_minus_K) < 1e-10) {
        return [
            'label_hasil' => $label_hasil,
            'label_lama' => $label_lama,
            'label_baru' => $label_baru,
            'matrix' => $matrix,
            'conflict_cells' => $conflict_cells,
            'K' => $K,
            'one_minus_K' => 0.0,
            'equations' => [],
            'result_mass' => ['THETA' => 1.0]
        ];
    }

    foreach ($grouped as $setKey => $products) {
        $sum = array_sum($products);
        $normalized = $sum / $one_minus_K;
        if ($normalized > 1e-10) {
            $result_mass[$setKey] = $normalized;
            $equations[] = [
                'set' => $setKey,
                'products' => $products,
                'sum' => $sum,
                'normalized' => $normalized
            ];
        }
    }

    // Urutkan equations: THETA di akhir, sisanya berdasarkan nilai normalized DESC
    usort($equations, function($a, $b) {
        if ($a['set'] === 'THETA') return 1;
        if ($b['set'] === 'THETA') return -1;
        return $b['normalized'] <=> $a['normalized'];
    });

    return [
        'label_hasil' => $label_hasil,
        'label_lama' => $label_lama,
        'label_baru' => $label_baru,
        'matrix' => $matrix,
        'conflict_cells' => $conflict_cells,
        'K' => $K,
        'one_minus_K' => $one_minus_K,
        'equations' => $equations,
        'result_mass' => $result_mass
    ];
}
