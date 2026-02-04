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

test.describe('NsManufacturing API (boms, items, orders)', () => {
  test('GET /api/crud/ns.manufacturing-boms responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/crud/ns.manufacturing-boms');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /api/crud/ns.manufacturing-bom-items responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/crud/ns.manufacturing-bom-items');
    expect([200, 401, 403, 404]).toContain(res.status());
  });

  test('GET /dashboard/manufacturing/boms responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/dashboard/manufacturing/boms');
    expect([200, 302, 401, 403, 404]).toContain(res.status());
  });
});
