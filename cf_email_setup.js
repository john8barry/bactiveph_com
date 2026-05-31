const https = require('https');

const API_TOKEN = process.env.CLOUDFLARE_API_TOKEN;
const ACCOUNT_ID = '94a4dc726399659300a4f4cc20f96cc9';
const ZONE_ID = '71ba04a85c07a74049896f0f1580469f';

const headers = {
  'Authorization': `Bearer ${API_TOKEN}`,
  'Content-Type': 'application/json'
};

async function request(method, path, body = null) {
  return new Promise((resolve, reject) => {
    const options = {
      hostname: 'api.cloudflare.com',
      port: 443,
      path: `/client/v4${path}`,
      method: method,
      headers: headers
    };

    const req = https.request(options, (res) => {
      let data = '';
      res.on('data', (chunk) => {
        data += chunk;
      });
      res.on('end', () => {
        try {
          const json = JSON.parse(data);
          resolve(json);
        } catch (e) {
          reject(e);
        }
      });
    });

    req.on('error', (e) => {
      reject(e);
    });

    if (body) {
      req.write(JSON.stringify(body));
    }
    req.end();
  });
}

async function run() {
  console.log('1. Adding destination address bactivedavao@gmail.com...');
  const addDestRes = await request('POST', `/accounts/${ACCOUNT_ID}/email/routing/addresses`, {
    email: 'bactivedavao@gmail.com'
  });
  console.log('Add Dest Result:', JSON.stringify(addDestRes, null, 2));

  console.log('\n2. Fetching current Email Routing settings/errors...');
  const routingRes = await request('GET', `/zones/${ZONE_ID}/email/routing`);
  
  if (!routingRes.success) {
    console.error('Failed to fetch routing info', routingRes.errors);
    return;
  }

  const errors = routingRes.result.errors || [];
  
  // Delete foreign records
  for (const err of errors) {
    if (err.existing && err.existing.id) {
      console.log(`Deleting foreign record ${err.existing.type} ${err.existing.content}...`);
      await request('DELETE', `/zones/${ZONE_ID}/dns_records/${err.existing.id}`);
    }
  }

  // Add missing records
  for (const err of errors) {
    if (err.missing) {
      console.log(`Adding missing record ${err.missing.type} ${err.missing.content}...`);
      await request('POST', `/zones/${ZONE_ID}/dns_records`, {
        type: err.missing.type,
        name: err.missing.name,
        content: err.missing.content,
        priority: err.missing.priority,
        ttl: err.missing.ttl
      });
    }
  }

  console.log('\n3. Enabling Email Routing...');
  const enableRes = await request('PUT', `/zones/${ZONE_ID}/email/routing`, {
    enabled: true
  });
  console.log('Enable Result:', JSON.stringify(enableRes, null, 2));

  console.log('\n4. Creating Custom Address rule hello@bactiveph.com -> bactivedavao@gmail.com...');
  const ruleRes = await request('POST', `/zones/${ZONE_ID}/email/routing/rules`, {
    matchers: [{ type: 'literal', field: 'to', value: 'hello@bactiveph.com' }],
    actions: [{ type: 'forward', value: ['bactivedavao@gmail.com'] }],
    enabled: true,
    name: 'Forward hello to bactivedavao'
  });
  console.log('Rule Result:', JSON.stringify(ruleRes, null, 2));
}

run().catch(console.error);
