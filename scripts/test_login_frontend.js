const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

(async () => {
  const artifactsDir = '/Users/e4gle/.gemini/antigravity/brain/d4663906-7550-4434-ba2f-d3f8b3c11c9f';
  if (!fs.existsSync(artifactsDir)) {
    fs.mkdirSync(artifactsDir, { recursive: true });
  }

  console.log('🚀 Khởi chạy trình duyệt Google Chrome...');
  const browser = await puppeteer.launch({
    headless: true,
    executablePath: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
    args: ['--no-sandbox', '--disable-setuid-sandbox']
  });

  const page = await browser.newPage();
  
  // Set viewport to a nice size
  await page.setViewport({ width: 1280, height: 800 });

  try {
    console.log('🌐 Điều hướng tới trang đăng nhập frontend: http://localhost:5173/login');
    await page.goto('http://localhost:5173/login', { waitUntil: 'networkidle2' });

    console.log('📸 Chụp ảnh màn hình trước khi đăng nhập...');
    const pathBefore = path.join(artifactsDir, 'login_before.png');
    await page.screenshot({ path: pathBefore });
    console.log(`✅ Đã chụp ảnh màn hình trước đăng nhập: ${pathBefore}`);

    console.log('✍️  Điền thông tin tài khoản admin...');
    await page.waitForSelector('#identifier');
    await page.type('#identifier', 'admin');
    await page.type('#password', 'admin123');

    console.log('📸 Chụp ảnh màn hình khi đã điền thông tin đăng nhập...');
    const pathFilled = path.join(artifactsDir, 'login_filled.png');
    await page.screenshot({ path: pathFilled });
    console.log(`✅ Đã chụp ảnh màn hình khi điền xong form: ${pathFilled}`);

    console.log('🖱️ Bấm nút Đăng Nhập...');
    await page.click('button[type="submit"]');

    console.log('⏳ Đợi hệ thống xử lý đăng nhập và chuyển trang...');
    // Chờ 5 giây để đảm bảo API phản hồi và LocalStorage được cập nhật
    await new Promise(resolve => setTimeout(resolve, 5000));

    // Lấy token và thông tin user từ LocalStorage
    const localStorageData = await page.evaluate(() => {
      return {
        accessToken: localStorage.getItem('access_token'),
        refreshToken: localStorage.getItem('refresh_token'),
        userInfo: localStorage.getItem('user_info'),
      };
    });

    console.log('📊 Trạng thái LocalStorage sau khi đăng nhập:');
    console.log(` - Access Token: ${localStorageData.accessToken ? 'Có (Đã lưu thành công)' : 'Không tìm thấy'}`);
    if (localStorageData.accessToken) {
      console.log(`   (Bắt đầu bằng: ${localStorageData.accessToken.substring(0, 30)}...)`);
    }
    console.log(` - Refresh Token: ${localStorageData.refreshToken ? 'Có (Đã lưu thành công)' : 'Không tìm thấy'}`);
    console.log(` - User Info:`, localStorageData.userInfo);

    console.log('📸 Chụp ảnh màn hình sau khi đăng nhập...');
    const pathAfter = path.join(artifactsDir, 'login_after.png');
    await page.screenshot({ path: pathAfter });
    console.log(`✅ Đã chụp ảnh màn hình sau đăng nhập: ${pathAfter}`);

    if (localStorageData.accessToken && localStorageData.userInfo) {
      console.log('\n🎉 KẾT LUẬN: Đăng nhập thành công và hoạt động 100% trên cả frontend lẫn backend!');
    } else {
      console.log('\n❌ KẾT LUẬN: Đăng nhập thất bại hoặc không lưu được dữ liệu xác thực.');
    }

  } catch (error) {
    console.error('❌ Lỗi trong quá trình kiểm thử:', error);
  } finally {
    console.log('🛑 Đóng trình duyệt...');
    await browser.close();
  }
})();
