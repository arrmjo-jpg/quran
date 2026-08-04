async function testLogin() {
  const res = await fetch('http://localhost:8080/api/v1/admin/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email: 'admin@quran.test', password: 'Pass123!' }),
  });
  const data = await res.json();
  console.log('Login Response Status:', res.status);
  console.log('Login Response Data:', JSON.stringify(data, null, 2));
}
testLogin();
