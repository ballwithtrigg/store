# TODO - Admin Auth

- [ ] Add admin auth guard to `admin.html` (redirect to `login.html` if not authenticated; add Logout)
- [ ] Implement login handling in `login.html` (fallback to hardcoded admin credentials) and set `adminAuthToken` in `localStorage`

- [ ] Update navigation links on pages so “Admin” points to `admin.html` only when authenticated, otherwise to `login.html`
- [ ] Quick manual test: open `admin.html` unauthenticated -> redirected; login as admin -> access granted; logout -> blocked again

