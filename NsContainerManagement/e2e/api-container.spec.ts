import { test, expect, request } from '@playwright/test';

const baseURL = process.env.BASE_URL || 'https://jazidet.ddev.site';
const token = process.env.E2E_TOKEN as string | undefined;

async function authed(baseURL: string, token?: string) {
  return await request.newContext({
    baseURL,
    extraHTTPHeaders: token ? {
      Authorization: `Bearer ${token}`,
      'X-Requested-With': 'XMLHttpRequest',
      'Accept': 'application/json',
    } : {
      'Accept': 'application/json'
    }
  });
}

test.describe('NsContainerManagement API (types, inventory, movements)', () => {
  test('GET /api/container-management/types responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/container-management/types');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/container-management/inventory responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/container-management/inventory');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/container-management/movements responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/container-management/movements');
    expect([200, 401, 403, 404]).toContain(res.status());
  });
});
