<!-- ============ BANNER ============ -->
<p align="center">
  <img src="assets/banner.png" alt="PURPLEHAZEL — a token-gated coordination layer for autonomous AI agents" width="100%">
</p>

<h1 align="center">PURPLEHAZEL</h1>

<p align="center">
  <b>A token-gated coordination layer for autonomous AI agents.</b><br>
  Seven specialist agents reason together, route work to one another, and act —<br>
  all from a polished web console, unlocked by holding <b>$PHZL</b>.
</p>

<p align="center">
  <img alt="Status" src="https://img.shields.io/badge/status-pre--launch-C425E3?style=for-the-badge">
  <img alt="Token" src="https://img.shields.io/badge/%24PHZL-Solana%20%C2%B7%20pump.fun-8a18a0?style=for-the-badge">
  <img alt="Stack" src="https://img.shields.io/badge/stack-PHP%20%2B%20HTML%2FJS-d97cf0?style=for-the-badge">
  <img alt="License" src="https://img.shields.io/badge/license-MIT-07060d?style=for-the-badge">
</p>

<p align="center">
  <a href="https://purplehazel.xyz">🌐 Website</a> &nbsp;·&nbsp;
  <a href="#-quick-start">⚡ Quick start</a> &nbsp;·&nbsp;
  <a href="#-the-seven-agents">🤖 The agents</a> &nbsp;·&nbsp;
  <a href="#-tokenomics-phzl">🪙 Tokenomics</a> &nbsp;·&nbsp;
  <a href="PRD.md">📄 PRD</a>
</p>

<p align="center">
  <a href="https://x.com/thepurplehazel"><img alt="X / Twitter" src="https://img.shields.io/badge/X-@thepurplehazel-07060d?style=flat-square&logo=x"></a>
  <a href="https://t.me/thepurplehazel"><img alt="Telegram" src="https://img.shields.io/badge/Telegram-thepurplehazel-229ED9?style=flat-square&logo=telegram&logoColor=white"></a>
  <a href="https://github.com/THEPURPLEHAZEL/PURPLEHAZEL"><img alt="GitHub" src="https://img.shields.io/badge/GitHub-THEPURPLEHAZEL-181717?style=flat-square&logo=github"></a>
</p>

---

## ✨ Overview

General-purpose, single-model assistants are jack-of-all-trades, master of none.
**PURPLEHAZEL** replaces them with a **mesh of seven specialist agents** — a market
scanner, signal hunter, social monitor, researcher, code runner, action executor and
an orchestrator that routes between them. The community **owns the tools it uses**:
network access is gated by holding the **$PHZL** token (or a paid tier).

It ships as three pieces backed by a lightweight PHP API:

| Piece | File | What it is |
|------|------|------------|
| 🛬 **Landing page** | `index.html` | 3D purple-haze hero, countdown, tokenomics, waitlist, login |
| 🖥️ **Agent console** | `dashboard.html` | Talk to the agent mesh, simulated trading, live metrics |
| 🛠️ **Admin panel** | `admin.html` | Key-gated stats: users, waitlist, sessions |

---

## 🖼️ Gallery

<p align="center">
  <img src="assets/gallery/1.png" width="24%" alt="Archangel — glitch">
  <img src="assets/gallery/2.png" width="24%" alt="Ascendant — radiant halo">
  <img src="assets/gallery/3.png" width="24%" alt="Rider — sword & lightning">
  <img src="assets/gallery/4.png" width="24%" alt="Imperator — the legion">
</p>

<p align="center"><i>The four art columns that cycle behind the hero. Drop your own PNGs into <code>assets/gallery/</code> and they're picked up automatically.</i></p>

---

## 🎛️ Features

- **3D haze hero** — Vanta.FOG + three.js (CDN) with a procedural lightning overlay and a clean CSS-gradient fallback if WebGL/CDN is unavailable.
- **Four cycling image columns** — random, non-duplicating art behind the title; fully data-driven via `COLUMN_IMAGES`.
- **Live launch countdown** + one-click **Copy Contract Address** and **Buy on pump.fun** (auto-enabled once the CA is set).
- **Dual login** — email **OTP** (bcrypt-hashed, rate-limited) *or* **Phantom wallet** signature → 7-day bearer session.
- **Agent terminal** — type a problem, keyword routing sends it to the right specialist, answered live via Claude.
- **Simulated trading desk** — live BTC price (CoinGecko), signals, order panel with a safety check, stop-loss & trailing-stop sims. *(Simulation only — no real execution.)*
- **Token gating** — verify on-chain $PHZL balance to unlock the free unlimited tier.
- **Cheap to host** — static front end + flat-file JSON store, runs on any shared PHP host. No database, no heavy infra.
- **Accessible & responsive** — respects `prefers-reduced-motion`, works desktop + mobile.

---

## 🤖 The Seven Agents

| Agent | Role |
|-------|------|
| 🧭 **Orchestrator** | Router — reads the request and dispatches to the right specialist |
| 📊 **Market Scanner** | Scans markets & pairs for conditions worth acting on |
| 🎯 **Signal Hunter** | Hunts entry/exit signals and setups |
| 📡 **Social Monitor** | Tracks social sentiment and narrative shifts |
| 🔬 **Research Agent** | Deep research and synthesis |
| 💻 **Code Runner** | Writes and reasons about code |
| ⚡ **Action Executor** | Turns decisions into (simulated) actions |

Each persona has its own system prompt in [`api/chat.php`](api/chat.php).

---

## 🪙 Tokenomics ($PHZL)

| Field | Value |
|------|-------|
| **Ticker** | `$PHZL` |
| **Network** | Solana |
| **Platform** | pump.fun |
| **Supply** | 1,000,000,000 *(placeholder — confirm)* |
| **Tax** | 0 / 0 *(placeholder — confirm)* |
| **Launch** | **Jun 7, 2026 · 18:00 UTC** |
| **Holder gate** | ≥ 1,000 $PHZL → free unlimited tier |

> After launch: set `CA_ADDRESS` in `index.html` (enables Copy + Buy) and
> `PHZL_MINT_ADDRESS` in `config.php` (enables holder verification). Both are single-line edits.

---

## ⚡ Quick start

> The front end is static HTML/JS; the API is plain PHP. No build step.

```bash
# 1. clone
git clone https://github.com/THEPURPLEHAZEL/PURPLEHAZEL.git
cd PURPLEHAZEL

# 2. create your real config from the example (config.php is gitignored)
cp config.example.php config.php
#    → fill in CLAUDE_API_KEY, SMTP_PASS, and generate fresh secrets:
#    php -r "echo bin2hex(random_bytes(32));"   # SESSION_SECRET
#    php -r "echo bin2hex(random_bytes(24));"   # ADMIN_KEY

# 3. run locally with PHP's built-in server
php -S localhost:8000

# 4. open it
open http://localhost:8000/
```

**Just want to preview the look** (no backend)? Serve statically — the page renders,
but login/chat/waitlist need PHP:

```bash
python3 -m http.server 8090
```

---

## 🔌 API endpoints (PHP)

| Endpoint | Method | Purpose | External dep |
|----------|--------|---------|--------------|
| `api/chat.php` | POST | Route a message to an agent persona → Claude | Anthropic API |
| `api/otp-send.php` | POST | Generate + email an OTP | SMTP mailbox |
| `api/otp-verify.php` | POST | Verify OTP, create user + session | — |
| `api/wallet-login.php` | POST | Login via Phantom wallet | — |
| `api/waitlist.php` | POST | Add email/wallet (anti-disposable + MX check) | — |
| `api/verify-holder.php` | POST | Check on-chain $PHZL balance vs threshold | Solana RPC |
| `api/me.php` | GET | Current session/user info | — |
| `api/admin.php` | GET | Admin stats/users/waitlist (key-gated) | — |

**Storage:** flat JSON files in `data/` (gitignored), shielded from web access by `data/.htaccess`.
**Auth:** OTP or Phantom signature → 64-byte bearer token, 7-day TTL.
**Security:** per-IP/action rate limiting, bcrypt-hashed OTPs, `hash_equals` for keys, disposable-email + MX validation, secrets live only in `config.php`.

---

## 📁 Project structure

```
purplehazel/
├── index.html            # landing page (hero, tokenomics, waitlist, login)
├── dashboard.html        # agent console (overview, agents, trading, settings)
├── admin.html            # key-gated admin panel
├── config.example.php    # copy → config.php, fill in your keys
├── api/                  # PHP backend
│   ├── chat.php          # agent routing → Claude
│   ├── otp-send.php / otp-verify.php
│   ├── wallet-login.php  # Phantom login
│   ├── waitlist.php
│   ├── verify-holder.php # on-chain $PHZL balance
│   ├── me.php / admin.php / admin-waitlist.php
├── assets/
│   ├── banner.png        # this README's banner
│   ├── logo.png          # brand mark
│   └── gallery/          # 1–4.png, the cycling hero art
├── data/                 # flat-file JSON store (gitignored, .htaccess-protected)
└── PRD.md                # full product requirements document
```

---

## 🚀 Deployment (shared PHP host / Hostinger)

1. Fill `CLAUDE_API_KEY` + `SMTP_PASS` in `config.php`; generate fresh `SESSION_SECRET` & `ADMIN_KEY`.
2. Create the mailbox `noreply@purplehazel.xyz`.
3. Upload everything to `public_html/` (keep `api/`, `assets/`, `data/.htaccess`).
4. Make `data/` writable (`755`).
5. Smoke test: waitlist → OTP email → console chat → admin panel.

---

## 🔒 Security note

`config.php` holds live secrets (Claude key, SMTP password, admin key, session secret)
and is **gitignored** — only `config.example.php` (placeholders) is committed.
**Never** commit your real `config.php`, and rotate any key that has ever been pushed.

---

## 🗺️ Roadmap

- [ ] Confirm real $PHZL supply & tax
- [x] Wire real social handles (X / Telegram / GitHub)
- [ ] Real Stripe billing for the Pro tier
- [ ] Real (non-simulated) trade execution
- [ ] Streaming responses in the agent terminal
- [ ] On-chain holder auto-refresh / per-request gating

---

## 📄 License

[MIT](LICENSE) © 2026 PURPLEHAZEL

<p align="center"><sub>Built with 💜 — $PHZL · Solana · pump.fun</sub></p>
