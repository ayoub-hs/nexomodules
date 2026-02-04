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

const describeOrSkip = token ? test.describe : test.describe.skip;

describeOrSkip('NsSpecialCustomer API (config, stats, crud)', () => {
  test('GET /api/special-customer/stats responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/special-customer/stats');
    expect([200, 401, 403]).toContain(res.status());
  });

  test('GET /api/crud/ns.outstanding-tickets responds', async () => {
    const api = await authed(baseURL, token);
    const res = await api.get('/api/crud/ns.outstanding-tickets');
    expect([200, 401, 403]).toContain(res.status());
  });
});
