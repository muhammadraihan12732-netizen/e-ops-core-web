import { useState } from 'react';
import { api } from '../api';

export default function Login({ onLogin }) {
  const [f, setF] = useState({ email: 'admin@eops.test', password: 'password' });
  const [err, setErr] = useState('');
  const submit = async (e) => {
    e.preventDefault();
    try {
      const d = await api('/login', { method: 'POST', body: f });
      localStorage.setItem('token', d.token);
      onLogin(d.user);
    } catch (x) { setErr(x.message); }
  };
  return (
    <div className="grid min-h-screen place-items-center p-4">
      <form onSubmit={submit} className="card w-full max-w-sm space-y-3">
        <div className="mx-auto grid h-12 w-12 place-items-center rounded-xl bg-accent font-black text-slate-900">EO</div>
        <h1 className="text-center text-base font-bold">E-Ops Core</h1>
        <p className="text-center text-[11px] text-slate-400">Enterprise Operation & Analytics Platform</p>
        <input className="input" type="email" placeholder="Email" value={f.email} onChange={(e) => setF({ ...f, email: e.target.value })} />
        <input className="input" type="password" placeholder="Password" value={f.password} onChange={(e) => setF({ ...f, password: e.target.value })} />
        {err && <p className="text-xs text-rose-400">{err}</p>}
        <button className="btn w-full">MASUK</button>
      </form>
    </div>
  );
}
