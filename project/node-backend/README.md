# FinanceIQ Node.js Backend

This folder adds a Node.js authentication backend for the FinanceIQ project.

## Run

```bash
cd node-backend
npm start
```

The server will run on:

```txt
http://localhost:3000
```

## Endpoints

- `POST /api/auth/register`
- `POST /api/auth/login`
- `GET /api/me`

Users are saved in:

```txt
node-backend/data/users.json
```

## Frontend

Open `test.html` in the browser. The frontend calls:

```txt
http://localhost:3000/api
```

If the Node server is not running, login/register will fall back to browser `localStorage` so the page does not show a fetch error.
