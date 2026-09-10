import { LicoraAdminApiError, LicoraAdminClient } from '../src/licora-admin-client.mjs';

const baseUrl = process.env.LICORA_ADMIN_API_BASE_URL;
const apiKey = process.env.LICORA_ADMIN_API_KEY;
if (!baseUrl || !apiKey) {
  console.error('Set LICORA_ADMIN_API_BASE_URL and LICORA_ADMIN_API_KEY.');
  process.exitCode = 2;
} else {
  const client = new LicoraAdminClient({ baseUrl, apiKey });

  try {
    const response = await client.createLicense({
      external_order_id: 'ORDER-10482',
      customer_reference: 'USER-9081',
      app_id: 'vibrapilot',
      validity_hours: 720,
      device_limit: 2,
      notes: 'Paid order',
    }, { idempotencyKey: 'checkout-ORDER-10482' });

    console.log(JSON.stringify(response, null, 2));
  } catch (error) {
    if (error instanceof LicoraAdminApiError) {
      console.error(JSON.stringify({
        code: error.code,
        http_status: error.httpStatus,
        request_id: error.requestId,
        message: error.message,
      }));
    } else {
      console.error('Unexpected integration failure.');
    }
    process.exitCode = 1;
  }
}

