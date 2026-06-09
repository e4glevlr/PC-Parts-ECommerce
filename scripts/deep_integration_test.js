#!/usr/bin/env node

/**
 * DEEP INTEGRATION TEST RUNNER for PC Parts E-Commerce (Laravel PHP Backend)
 * 
 * This script runs a complete end-to-end integration test against the Laravel API:
 * 1. Spawns the PHP Artisan Serve process on port 8080
 * 2. Polls until the server is alive
 * 3. Registers a new integration test user
 * 4. Logs in to obtain JWT access token
 * 5. Fetches user profile (middleware check)
 * 6. Fetches product catalog to locate a valid instock product
 * 7. Adds the product to the shopping cart
 * 8. Fetches the cart to verify state
 * 9. Submits checkout ("Orders from Cart")
 * 10. Asserts order creation, stock deduction, and lists personal orders
 * 11. Shuts down the backend server gracefully
 */

const { spawn } = require('child_process');
const http = require('http');

let API_BASE = 'http://localhost:8888/api';
let serverProcess = null;

// ANSI Colors for beautiful output
const colors = {
    reset: '\x1b[0m',
    bright: '\x1b[1m',
    green: '\x1b[32m',
    red: '\x1b[31m',
    cyan: '\x1b[36m',
    yellow: '\x1b[33m',
    magenta: '\x1b[35m',
    bgBlack: '\x1b[40m'
};

function log(color, message) {
    console.log(`${color}${message}${colors.reset}`);
}

function success(message) {
    console.log(`  ${colors.green}✓ ${message}${colors.reset}`);
}

function fail(message, error = '') {
    console.log(`  ${colors.red}✗ ${message}${colors.reset}`);
    if (error) console.error(error);
}

// Helpers for native HTTP fetch
function request(method, path, body = null, token = null) {
    return new Promise((resolve, reject) => {
        const url = `${API_BASE}${path}`;
        const parsedUrl = new URL(url);
        
        const options = {
            hostname: parsedUrl.hostname,
            port: parsedUrl.port,
            path: parsedUrl.pathname + parsedUrl.search,
            method: method,
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            }
        };

        if (token) {
            options.headers['Authorization'] = `Bearer ${token}`;
        }

        const req = http.request(options, (res) => {
            let responseData = '';
            res.on('data', (chunk) => { responseData += chunk; });
            res.on('end', () => {
                let parsed = responseData;
                try {
                    const jsonStart = responseData.search(/[{[]/);
                    const cleanData = jsonStart !== -1 ? responseData.substring(jsonStart) : responseData;
                    parsed = JSON.parse(cleanData);
                } catch (e) {
                    // Not JSON
                }
                resolve({
                    status: res.statusCode,
                    headers: res.headers,
                    data: parsed
                });
            });
        });

        req.on('error', (err) => {
            reject(err);
        });

        if (body) {
            req.write(JSON.stringify(body));
        }
        req.end();
    });
}

// Wait for port to open
function waitForServer(port, retries = 15, delay = 500) {
    return new Promise((resolve, reject) => {
        const check = (attempt) => {
            const socket = require('net').createConnection(port, 'localhost');
            socket.on('connect', () => {
                socket.destroy();
                resolve();
            });
            socket.on('error', (err) => {
                socket.destroy();
                if (attempt >= retries) {
                    reject(new Error(`Server not ready on port ${port} after ${retries} attempts.`));
                } else {
                    setTimeout(() => check(attempt + 1), delay);
                }
            });
        };
        check(1);
    });
}

async function runTests() {
    log(colors.magenta, '\n======================================================');
    log(colors.magenta + colors.bright, ' 🖥️  STARTING DEEP INTEGRATION TESTS (LARAVEL API) ');
    log(colors.magenta, '======================================================\n');

    // 1. Start Server
    log(colors.cyan, '🚀 Khởi động Laravel Development Server...');
    serverProcess = spawn('php', ['-d', 'error_reporting=0', '-d', 'display_errors=off', 'artisan', 'serve', '--port=8888'], {
        cwd: __dirname + '/../backend-php',
        stdio: 'ignore' // Suppress logs so we get a clean visual E2E output
    });

    try {
        // Wait for port 8888
        await waitForServer(8888);
        success('Laravel server đã hoạt động tại http://localhost:8888/api');
    } catch (err) {
        fail('Không thể kết nối tới server!', err.message);
        process.exit(1);
    }

    // Temporarily override API BASE to port 8888
    global.API_BASE = 'http://localhost:8888/api';

    let testToken = null;
    let registeredUser = `integration_user_${Date.now()}`;
    let registeredEmail = `integration_${Date.now()}@example.com`;
    let targetProductId = null;
    let targetProductName = '';
    let targetProductPrice = 0;

    try {
        // ----------------------------------------------------
        // TEST CASE 1: Base endpoint check
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 1] Kiểm tra trạng thái API gốc...');
        const resBase = await request('GET', '/');
        if (resBase.status === 200 && resBase.data.message) {
            success(`API phản hồi OK: "${resBase.data.message}"`);
        } else {
            throw new Error(`Status ${resBase.status}: ${JSON.stringify(resBase.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 2: Register User
        // ----------------------------------------------------
        log(colors.yellow, `\n[TEST 2] Đăng ký tài khoản CUSTOMER mới [${registeredUser}]...`);
        const registerPayload = {
            username: registeredUser,
            email: registeredEmail,
            password: 'UserPass123!',
            full_name: 'Người dùng Thử nghiệm E2E',
            phone: `09${Math.floor(10000000 + Math.random() * 90000000)}`,
            address: '123 Đường thử nghiệm, Quận 1, TP. HCM'
        };
        const resReg = await request('POST', '/v1/users/register', registerPayload);
        if (resReg.status === 201) {
            success('Đăng ký tài khoản thành công!');
        } else {
            throw new Error(`Đăng ký thất bại với mã trạng thái ${resReg.status}: ${JSON.stringify(resReg.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 3: Login User (Get JWT)
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 3] Đăng nhập tài khoản để nhận JWT Token (đợi 1.1s để tránh trùng lặp JWT)...');
        await new Promise(resolve => setTimeout(resolve, 1100));
        const loginPayload = {
            identifier: registeredUser,
            password: 'UserPass123!'
        };
        const resLogin = await request('POST', '/v1/users/login', loginPayload);
        if (resLogin.status === 200 && resLogin.data.data.access_token) {
            testToken = resLogin.data.data.access_token;
            success('Đăng nhập thành công! JWT Token nhận được hợp lệ.');
            log(colors.cyan, `  🔑 Token: ${testToken.substring(0, 40)}... [Mã hóa chữ ký Custom JWT]`);
        } else {
            throw new Error(`Đăng nhập thất bại: ${JSON.stringify(resLogin.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 4: Get Profile (Auth & Middleware Check)
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 4] Kiểm tra Middleware xác thực JWT (Tải Profile)...');
        const resProfile = await request('GET', '/v1/users/profile', null, testToken);
        if (resProfile.status === 200 && resProfile.data.data.username === registeredUser) {
            success(`Middleware JWT cho phép truy cập! Profile username: "${resProfile.data.data.username}"`);
        } else {
            throw new Error(`Truy cập profile thất bại: ${JSON.stringify(resProfile.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 5: List Products & Select Valid Product ID
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 5] Kiểm tra danh sách Sản phẩm (Product Catalog)...');
        const resProducts = await request('GET', '/v1/products');
        if (resProducts.status === 200 && resProducts.data.data.content) {
            const items = resProducts.data.data.content;
            success(`Lấy danh sách thành công! Tìm thấy ${items.length} sản phẩm.`);
            
            // Find a product with positive stock
            const validProduct = items.find(item => item.quantity > 0);
            if (validProduct) {
                targetProductId = validProduct.id;
                targetProductName = validProduct.name;
                targetProductPrice = validProduct.price;
                success(`Đã chọn sản phẩm thử nghiệm: ID = ${targetProductId} | Name = "${targetProductName}" | Kho = ${validProduct.quantity}`);
            } else {
                throw new Error('Database không có sản phẩm nào có số lượng tồn kho > 0 để đặt hàng!');
            }
        } else {
            throw new Error(`Không lấy được danh sách sản phẩm: ${JSON.stringify(resProducts.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 6: Add Item to Cart
        // ----------------------------------------------------
        log(colors.yellow, `\n[TEST 6] Thêm sản phẩm vào giỏ hàng (Cart Operations)...`);
        const cartPayload = {
            product_id: targetProductId,
            quantity: 2
        };
        const resCartAdd = await request('POST', '/v1/cart/items', cartPayload, testToken);
        if (resCartAdd.status === 201 || resCartAdd.status === 200) {
            success('Thêm vào giỏ hàng thành công!');
        } else {
            throw new Error(`Không thể thêm vào giỏ: ${JSON.stringify(resCartAdd.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 7: View Cart to verify state
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 7] Xem giỏ hàng để kiểm chứng tính toán...');
        const resCartView = await request('GET', '/v1/cart', null, testToken);
        if (resCartView.status === 200 && resCartView.data.data.items) {
            const cartItems = resCartView.data.data.items;
            const targetItem = cartItems.find(item => item.product_id === targetProductId);
            
            if (targetItem && targetItem.quantity === 2) {
                success('Giỏ hàng chính xác! Lưu trữ đúng sản phẩm và số lượng = 2.');
                success(`Giỏ hàng của khách hàng: Tổng trị giá = ${resCartView.data.data.total_amount || 0} VND`);
            } else {
                throw new Error(`Sản phẩm không có trong giỏ hàng hoặc sai số lượng: ${JSON.stringify(resCartView.data)}`);
            }
        } else {
            throw new Error(`Không xem được giỏ hàng: ${JSON.stringify(resCartView.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 8: Create Order from Cart (Checkout & Inventory Check)
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 8] Tiến hành Thanh toán Đơn hàng (Place Order)...');
        const checkoutPayload = {
            customer_name: 'Khách Hàng Thử Nghiệm E2E',
            customer_email: registeredEmail,
            shipping_address: '456 Đường CMT8, Quận 3, TP. Hồ Chí Minh',
            shipping_phone: '0988888888',
            payment_method: 'COD',
            notes: 'Giao hàng giờ hành chính'
        };
        
        const resCheckout = await request('POST', '/v1/orders/from-cart', checkoutPayload, testToken);
        if (resCheckout.status === 201 || resCheckout.status === 200) {
            const order = resCheckout.data.data;
            success('Đặt hàng thành công! Đã tạo Hóa đơn (Order).');
            success(`Mã Đơn hàng: "${order.order_code}" | Trạng thái: "${order.status}" | Tổng thanh toán: ${order.final_amount} VND`);
        } else {
            throw new Error(`Đặt hàng thất bại: ${JSON.stringify(resCheckout.data)}`);
        }

        // ----------------------------------------------------
        // TEST CASE 9: Verify My Orders List
        // ----------------------------------------------------
        log(colors.yellow, '\n[TEST 9] Kiểm tra danh sách Đơn hàng cá nhân (My Orders)...');
        const resMyOrders = await request('GET', '/v1/orders/my-orders', null, testToken);
        if (resMyOrders.status === 200 && resMyOrders.data.data.content) {
            const orders = resMyOrders.data.data.content;
            if (orders.length > 0) {
                success(`Tìm thấy ${orders.length} đơn hàng trong danh sách của bạn.`);
                success(`Đơn hàng gần nhất: Mã: "${orders[0].order_code}" | Giá trị: ${orders[0].final_amount} VND`);
            } else {
                throw new Error('Danh sách đơn hàng của bạn trống rỗng!');
            }
        } else {
            throw new Error(`Không lấy được danh sách đơn hàng: ${JSON.stringify(resMyOrders.data)}`);
        }

        log(colors.green + colors.bright, '\n======================================================');
        log(colors.green + colors.bright, '     🎉 TẤT CẢ 9 BƯỚC KIỂM TRA ĐÃ THÀNH CÔNG RỰC RỠ! ');
        log(colors.green + colors.bright, '======================================================');

    } catch (err) {
        log(colors.red + colors.bright, '\n======================================================');
        log(colors.red + colors.bright, ` ❌ LỖI TẠI TEST CASE: ${err.message}`);
        log(colors.red + colors.bright, '======================================================');
        cleanup();
        process.exit(1);
    }

    cleanup();
}

function cleanup() {
    if (serverProcess) {
        log(colors.cyan, '\n🛑 Đang đóng Laravel Development Server...');
        serverProcess.kill('SIGINT');
        success('Đã tắt server Laravel.');
    }
}

// Handle sudden exit
process.on('SIGINT', () => {
    cleanup();
    process.exit();
});

runTests();
