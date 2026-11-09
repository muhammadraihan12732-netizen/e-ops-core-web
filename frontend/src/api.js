export const BASE = import.meta.env.VITE_API_URL || 'http://localhost:8000/api';

function headers(extra = {}) {
  const token = localStorage.getItem('token');
  return { Accept: 'application/json', ...(token && { Authorization: 'Bearer ' + token }), ...extra };
}

// Token ditolak server (kedaluwarsa / akun dinonaktifkan) -> App akan kembali ke halaman login.
function unauthorized(res) {
  if (res.status === 401) window.dispatchEvent(new Event('eops:unauthorized'));
}

export async function api(path, { method = 'GET', body } = {}) {
  let res;
  try {
    res = await fetch(BASE + path, {
      method,
      headers: headers({ 'Content-Type': 'application/json' }),
      body: body && JSON.stringify(body),
    });
  } catch {
    throw Object.assign(new Error('Tidak dapat terhubung ke server. Periksa koneksi atau pastikan backend berjalan.'), { status: 0 });
  }
  const data = await res.json().catch(() => ({}));
  if (!res.ok) {
    if (path !== '/login') unauthorized(res);
    const msg = data.errors ? Object.values(data.errors).flat()[0] : data.message;
    throw Object.assign(new Error(msg || 'Terjadi kesalahan'), { status: res.status });
  }
  return data;
}

// Unduh file hanya jika respons sukses, sehingga halaman error tidak ikut tersimpan sebagai .csv
export async function download(path, filename) {
  let res;
  try { res = await fetch(BASE + path, { headers: headers() }); }
  catch { throw new Error('Tidak dapat terhubung ke server.'); }
  if (!res.ok) {
    unauthorized(res);
    const d = await res.json().catch(() => ({}));
    throw new Error(d.message || `Ekspor gagal (HTTP ${res.status})`);
  }
  const url = URL.createObjectURL(await res.blob());
  const a = Object.assign(document.createElement('a'), { href: url, download: filename });
  a.click();
  URL.revokeObjectURL(url);
}
