# Pokémon Voting System

A minimal, production‑deployed LAMP-style voting application built with PHP, Apache, PostgreSQL, and Docker. Users are presented with two random Pokémon and vote for their preferred choice. Votes are persisted and reflected in a live leaderboard.

This project demonstrates:

- Dockerized PHP + Apache setup
- Managed PostgreSQL integration (Render)
- Environment-based configuration
- Server-rendered PHP architecture
- Production deployment workflow

---

## 🚀 Live Application

Deployed on Render (Web Service + Managed PostgreSQL).

---

## 🏗 Architecture

### Stack

- **PHP 8.2 (Apache)**
- **PostgreSQL 15**
- **Docker** (local development)
- **Render** (production hosting)

### Application Structure

```
src/
  app/              # Domain logic (optional expansion area)
  config/           # Database configuration
  public/           # Public web root (Apache document root)
  scripts/          # CLI-only utilities (optional)

 docker/
   php/             # Dockerfile for Apache + PHP

 data/              # Local seed data (not deployed)
```

Only the `public/` directory is exposed to the web server in production.

---

## ⚙️ Local Development

### 1️⃣ Start Docker

```bash
docker compose up -d
```

### 2️⃣ Access Application

```
http://localhost:8080
```

### 3️⃣ Access PostgreSQL

```bash
docker compose exec db psql -U postgres -d pokedex
```

---

## 🗄 Database Setup

### Run Schema

```bash
psql "postgres://user:pass@host:5432/dbname" -f schema.sql
```

Or inside psql:

```sql
\i schema.sql
```

### Seed Data

Seeding is performed via CLI-only scripts using JSON data stored outside the web root.

Seed files are intentionally not exposed publicly.

---

## 🌎 Production Deployment (Render)

### Environment Variable

Set the following in Render:

```
DATABASE_URL=postgres://user:password@host:5432/dbname
```

The application automatically detects and parses `DATABASE_URL` for production.

### Important

- Do NOT use `localhost` as DB host in production.
- Use Render's **Internal Database URL**.
- Clear build cache on deploy if Dockerfile changes.

---

## 🔐 Security Considerations

- `.env` is excluded via `.gitignore`
- Seed routes are not publicly accessible
- Production DB credentials are stored in Render environment variables
- Only `public/` is served by Apache

---

## 📈 Future Improvements

Potential enhancements:

- Elo rating system
- Vote rate limiting
- Session/IP tracking
- Analytics dashboard
- AJAX-based voting (no page reload)
- Caching leaderboard queries

---

## 🎯 Purpose

This project serves as a hands-on exploration of:

- Backend architecture fundamentals
- Containerization
- Production database configuration
- Infrastructure-aware development

It is intentionally framework-light to emphasize core concepts.

---

## 🧪 Development Notes

- Volumes are used locally for hot reloading.
- Production builds copy `src/` directly into the container.
- Seeder utilities remain outside the web root.

---

## 📝 License

This project is for educational and portfolio purposes.
Pokémon data sourced from PokéAPI.

---

Built as a full-stack learning project exploring modern deployment patterns using traditional server-rendered architecture.

