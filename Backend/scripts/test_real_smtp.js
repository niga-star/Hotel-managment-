const nodemailer = require('nodemailer');

async function testRealSmtp() {
  console.log('Testing real SMTP connection...');
  try {
    const testAccount = await nodemailer.createTestAccount();
    console.log('Real Ethereal SMTP test account initialized:');
    console.log('User:', testAccount.user);
    console.log('SMTP Host:', testAccount.smtp.host, 'Port:', testAccount.smtp.port);

    const transporter = nodemailer.createTransport({
      host: testAccount.smtp.host,
      port: testAccount.smtp.port,
      secure: testAccount.smtp.secure,
      auth: {
        user: testAccount.user,
        pass: testAccount.pass
      }
    });

    const info = await transporter.sendMail({
      from: '"Ethiopian Boutique Luxury Stays" <reservations@ethiopianhotel.com>',
      to: 'guest_real_test@ethin.com',
      subject: 'Your 6-Digit Email Verification Code: 489201',
      text: 'Your verification code is 489201. It expires in 15 minutes.',
      html: '<h2>Your Verification Code</h2><p style="font-size: 24px; font-weight: bold; color: #0B4D34;">489201</p>'
    });

    console.log('✅ Real SMTP Email sent successfully!');
    console.log('Message ID:', info.messageId);
    console.log('Accepted Recipients:', info.accepted);
    console.log('Real Delivery Preview URL:', nodemailer.getTestMessageUrl(info));
    return true;
  } catch (err) {
    console.error('❌ Real SMTP Delivery Failed:', err);
    return false;
  }
}

testRealSmtp().then(() => process.exit(0));
