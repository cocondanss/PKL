<?php
require 'function.php';

// Modify the query to only fetch visible products
$query = "SELECT * FROM products WHERE visible = 1";
$result = mysqli_query($conn, $query);
$products = mysqli_fetch_all($result, MYSQLI_ASSOC);

if (!$conn) {
    die("Koneksi gagal: " . mysqli_connect_error());
}


// Fungsi untuk menerapkan voucher
function applyVoucher($voucherCode, $price) {
    global $conn;

    $stmt = $conn->prepare("SELECT * FROM vouchers2 WHERE code = ?");
    if (!$stmt) {
        return array(
            'original_price' => $price,
            'discounted_price' => $price,
            'has_discount' => false
        );
    }

    $stmt->bind_param("s", $voucherCode);
    if (!$stmt->execute()) {
        return array(
            'original_price' => $price,
            'discounted_price' => $price,
            'has_discount' => false
        );
    }

    $queryResult = $stmt->get_result();
    if ($row = $queryResult->fetch_assoc()) {
        if ($row['one_time_use'] == 1 && $row['used_at'] !== null) {
            return array(
                'original_price' => $price,
                'discounted_price' => $price,
                'has_discount' => false
            );
        }

        $discountAmount = $row['discount_amount'];
        
        // Cek apakah diskon dalam bentuk persentase atau nominal
        if ($discountAmount <= 100) {
            // Diskon persentase
            $discountedPrice = $price - ($price * ($discountAmount / 100));
        } else {
            // Diskon nominal
            $discountedPrice = max($price - $discountAmount, 0);
        }

        if ($discountedPrice < $price) {
            return array(
                'original_price' => $price,
                'discounted_price' => $discountedPrice,
                'has_discount' => true
            );
        }
    }

    return array(
        'original_price' => $price,
        'discounted_price' => $price,
        'has_discount' => false
    );
}

// Handle AJAX request untuk voucher
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['voucher_code'])) {
    header('Content-Type: application/json');
    
    $response = array(
        'success' => false,
        'message' => '',
        'products' => array()
    );
    
    try {
        $voucherCode = $_POST['voucher_code'];
        
        // Cek voucher
        $stmt = $conn->prepare("SELECT * FROM vouchers2 WHERE code = ?");
        $stmt->bind_param("s", $voucherCode);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Cek apakah voucher sudah digunakan
            if ($row['one_time_use'] == 1 && $row['used_at'] !== null) {
                $response['message'] = 'Voucher sudah digunakan';
                echo json_encode($response);
                exit;
            }

            // Terapkan voucher ke semua produk
            foreach ($products as $product) {
                $priceInfo = applyVoucher($voucherCode, $product['price']);
                $response['products'][] = array(
                    'id' => $product['id'],
                    'original_price' => $product['price'],
                    'discounted_price' => $priceInfo['discounted_price'],
                    'has_discount' => $priceInfo['has_discount']
                );
            }
            
            // Update status voucher saat berhasil digunakan
            $currentTime = date('Y-m-d H:i:s');
            $updateStmt = $conn->prepare("UPDATE vouchers2 SET used_at = ?, is_used = 1 WHERE code = ?");
            $updateStmt->bind_param("ss", $currentTime, $voucherCode);
            $updateStmt->execute();
            
            // Simpan voucher code dalam session untuk digunakan nanti
            $_SESSION['pending_voucher'] = $voucherCode;
            
            $response['success'] = true;
            $response['message'] = 'Voucher berhasil diterapkan';
        } else {
            $response['message'] = 'Voucher tidak valid';
        }
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        error_log("Error in voucher processing: " . $e->getMessage());
    }
    
    echo json_encode($response);
    exit;
}
$voucherCode = '';
$voucherMessages ='';

// Ambil data produk
$produk = mysqli_query($conn, "SELECT * FROM products");
if (!$produk) {
    die("Query gagal: " . mysqli_error($conn));
}
?>


<!doctype html>
<html lang="en">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product List</title>
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700' rel='stylesheet'>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" />
    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
        }

        .container-index, .container-submit-index {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            text-align: left;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            min-height: 100vh;
        }

        .content-submit {
            padding-top: 20px;
        }

        .content-wrapper, .content-wrapper-submit {
            width: 100%;
            max-width: 1200px;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            overflow-y: auto;
            margin: 0 auto;
            text-align: left;
            justify-content: center;
            height: 100vh;
            flex: 1;
        }

        .product-list {
            display: flex;
            gap: 20px;
            width: 300%;
            max-width: 1200px;
            margin: 0 auto;
        }
        .products-container {
            flex: 3;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        .product, .product-submit {
            background-color: #2b2d42;
            color: white;
            border-radius: 10px;
            padding: 20px;
            height: 230px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            text-align: left;
        }

        .product {
            margin-left: 100px;
            margin-right: 40px;
        }

        .product-info {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            width: calc(100% - 100px);
            height: 100%;
        }

        .product-info h2 {
            margin-top: 0;
            font-size: 24px;
            word-wrap: break-word; 
            max-width: 100%;
        }

        .product-info p {
            margin: 10px 0;
            word-wrap: break-word; 
            max-width: 100%;
        }

        .product button, .product-submit button {
            background-color: #d3d3d3;
            color: #2b2d42;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
            align-self: flex-end;
            min-width: 80px;
            white-space: nowrap;
        }

        .product button:hover, .product-submit button:hover {
            background-color: #b0b0b0;
        }

        #modal-price {
            font-size: 24px;
            font-weight: bold;
            color: #2b2d42;
            margin-bottom: 10px;
            text-align: center;
        }

        .product.price-changed {
            animation: highlight 1s ease-in-out;
        }

        .product-info .original-price {
            text-decoration: line-through;
            color: #a0a0a0;
        }

        .product-info .discounted-price {
            color: white;
            font-weight: bold;
        }

        .voucher-form {
            flex: 1;
            border-radius: 10px;
            padding: 20px;
            height: 100px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .voucher-form input[type="text"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }

        .voucher-form button[type="submit"] {
            width: 500px;
            padding: 10px;
            background-color: #2b2d42;
            color: #ffffff;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: bold;
        }

        .voucher-form button[type="submit"]:hover {
            background-color: #b0b0b0;
        }

        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.5);
        }

        .modal-body {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .calculator-container {
            text-align: center;
        }

        .calculator {
            width: 250px;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            background-color: #ffffff;
            margin: 0 auto 20px;
        }

        .display {
            width: 100%;
            height: 50px;
            background-color: #6c757d;
            color: #ffffff;
            text-align: center;
            line-height: 50px;
            border-radius: 10px;
            margin-bottom: 20px;                
            font-size: 24px;
        }

        .btn {
            width: 60px;
            height: 60px;
            margin: 5px;
            font-size: 24px;
            border-radius: 10px;
        }

        .btn-number {
            background-color: #6c757d;
            color: #ffffff;
        }

        .btn-backspace {
            background-color: #dc3545;
            color: #ffffff;
        }

        .btn-enter {
            background-color: #28a745;
            color: #ffffff;
        }

        .modal-content {
            background-color: rgba(0, 0, 0, 0);
            border: #28a745;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            margin-bottom: 0;
        }

        .modal-footer .btn {
            padding: 0.375rem 0.75rem;
            font-size: 1rem;
        }

        .back-button {
            width: 70%;
            max-width: 220px;
        }

        .payment-method {
            text-align: center;
        }

        #voucher-form {
            width: 100%;
            padding: 20px;
            margin-bottom: 60px;
            border-radius: 5px;
        }

        #product-list {
            position: relative;
            top: 0;
            left: 0;
        }

        #product-container {
            max-width: 1200px;
            padding: 20px;
            flex: 1;
            overflow-y: auto;
            margin-top: 10px;
        }

        h1.product-list-title {
            margin-bottom: 10px;
            font-size: 24px;
            color: #333;
            padding: 10px 0;
            border-bottom: 2px solid #eee;
        }

        .price-container {
            min-height: 50px; 
            transition: all 0.3s ease;
        }

        #voucher-message-container {
            transition: opacity 0.5s ease-in-out;
        }

        .voucher-message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
        }

        .voucher-message.error {
            background-color: #ffecec;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .voucher-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        /* QR Modal Styles */
        .qr-modal .modal-content {
            background-color: #ffffff;
            border-radius: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            border: none;
            overflow: hidden;
        }

        .qr-modal .modal-header {
            background-color: #2b2d42;
            color: white;
            border-bottom: none;
            padding: 20px 30px;
        }

        .qr-modal .modal-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            font-family: 'Poppins', sans-serif;
        }

        .qr-modal .btn-close {
            background-color: transparent;
            border: 2px solid white;
            border-radius: 50%;
            padding: 8px;
            opacity: 1;
        }

        .qr-modal .btn-close:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .qr-modal .modal-body {
            padding: 30px;
            text-align: center;
            background-color: #f8f9fa;
        }

        .qr-modal .qr-code-container {
            background-color: #ffffff;
            border-radius: 15px;
            padding: 20px;
            display: inline-block;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
        }

        .qr-modal .qr-code-image {
            max-width: 250px;
            height: auto;
            border-radius: 10px;
            display: block;
            margin: 0 auto;
        }

        .qr-modal #countdown {
            font-size: 20px;
            font-weight: bold;
            color: #2b2d42;
            margin: 15px 0;
            font-family: 'Poppins', sans-serif;
            background: #ffffff;
            padding: 8px 15px;
            border-radius: 8px;
            display: inline-block;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }

        .qr-modal .status-message {
            margin: 15px 0;
            min-height: 30px;
        }

        .qr-modal .status-message .alert {
            border-radius: 8px;
            padding: 10px 15px;
            margin: 0;
            font-weight: 500;
        }

        .qr-modal .button-container {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 15px;
        }

        .qr-modal .btn {
            padding: 10px 25px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 15px;
            min-width: 160px;
        }

        .qr-modal .btn-cancel {
            background-color: #2b2d42;
            color: white;
            border: none;
        }

        .qr-modal .btn-cancel:hover {
            background-color: #1a1b2e;
            transform: translateY(-2px);
        }

        .qr-modal #btn-check {
            background-color: #e9ecef;
            color: #2b2d42;
            border: 2px solid #2b2d42;
        }

        .qr-modal #btn-check:hover {
            background-color: #2b2d42;
            color: white;
            transform: translateY(-2px);
        }

        .qr-modal .btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        /* Loading spinner styles */
        .qr-modal .spinner-border {
            width: 1.2rem;
            height: 1.2rem;
            margin-right: 8px;
        }

        /* Modal backdrop style */
        .modal-backdrop {
            background-color: rgba(0, 0, 0, 0.7);
        }

        /* Responsive styles */
        @media (max-width: 576px) {
            .qr-modal .modal-body {
                padding: 20px;
            }

            .qr-modal .qr-code-image {
                max-width: 200px;
            }

            .qr-modal .button-container {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .qr-modal .btn {
                padding: 8px 20px;
                min-width: 140px;
            }
        }

        /* Animation styles */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .qr-modal.show {
            animation: fadeIn 0.3s ease-out;
        }

        /* Alert styles */
        .qr-modal .alert-success {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
        }

        .qr-modal .alert-warning {
            background-color: #fff3cd;
            border-color: #ffeeba;
            color: #856404;
        }

        .qr-modal .alert-danger {
            background-color: #f8d7da;
            border-color: #f5c6cb;
            color: #721c24;
        }
    </style>
</head>
<body>
<div class="container-submit-index">
    <div class="content-submit">
        <h1 class="product-list-title">Product List</h1>
        <div class="container-button">
            <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#keypadModal" style="position: absolute; right: 30px; top: 30px; background: none; border: none;">
                <i class="fas fa-lock" style="font-size: 25px; color: rgba(0, 0, 0, 0.2);"></i>
            </button>
        </div>
        <div id="voucher-message-container">
            <!-- <?php
            // foreach ($voucherMessages as $message) {
            //     echo $message;
            // }
            ?> -->
        </div>
        <div class="product-list">
            <div class="products-container"> 
                <?php 
                $counter = 0;
                foreach ($products as $item): 
                    $originalPrice = $item['price']; 
                    $discountedPrice = applyVoucher($voucherCode, $originalPrice); 
                    if ($counter < 3):
                ?> 
                  <div class="product-submit"> 
                        <h2><?php echo htmlspecialchars($item['name']); ?></h2> 
                        <div id="price-container-<?php echo $item['id']; ?>" class="price-container">
                            <?php 
                            $priceInfo = applyVoucher($voucherCode, $item['price']);
                            if (is_array($priceInfo) && isset($priceInfo['has_discount']) && $priceInfo['has_discount']): 
                            ?>
                                <p class="original-price">Rp <?php echo number_format($priceInfo['original_price'], 0, ',', '.'); ?></p>
                                <p class="discounted-price">Rp <?php echo number_format($priceInfo['discounted_price'], 0, ',', '.'); ?></p>
                            <?php else: ?>
                                <p>Rp <?php echo number_format(is_array($priceInfo) ? $priceInfo['original_price'] : $item['price'], 0, ',', '.'); ?></p>
                            <?php endif; ?>
                        </div>
                        <p id="description-<?php echo $item['id']; ?>"> 
                            <?php echo htmlspecialchars($item['description']); ?> 
                        </p> 
                        <button onclick="showPaymentModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars($item['name']); ?>', <?php echo is_array($priceInfo) ? $priceInfo['discounted_price'] : $item['price']; ?>)">Buy</button> 
                    </div>
                <?php 
                    endif;
                    $counter++;
                endforeach; 
                ?>
                <div class="voucher-form">
                    <form id="voucher-form" method="POST">
                        <input type="text" name="voucher_code" placeholder="Masukkan kode voucher">
                        <button type="submit">Terapkan Voucher</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
        <div class="modal fade" id="keypadModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="calculator">
                            <div class="display" id="display"></div>
                            <div class="d-flex flex-wrap justify-content-center">
                                <button class="btn btn-number" onclick="appendNumber('1')">1</button>
                                <button class="btn btn-number" onclick="appendNumber('2')">2</button>
                                <button class="btn btn-number" onclick="appendNumber('3')">3</button>
                                <button class="btn btn-number" onclick="appendNumber('4')">4</button>
                                <button class="btn btn-number" onclick="appendNumber('5')">5</button>
                                <button class="btn btn-number" onclick="appendNumber('6')">6</button>
                                <button class="btn btn-number" onclick="appendNumber('7')">7</button>
                                <button class="btn btn-number" onclick="appendNumber('8')">8</button>
                                <button class="btn btn-number" onclick="appendNumber('9')">9</button>
                                <button class="btn btn-backspace" onclick="backspace()"><i
                                        class="fas fa-backspace"></i></button>
                                <button class="btn btn-number" onclick="appendNumber('0')">0</button>
                                <button class="btn btn-enter" onclick="enter()"><i class="fas fa-check"></i></button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/js/all.min.js"></script>
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
            let pinCode = '';
            let display = document.getElementById('display');

            function appendNumber(number) {
                if (pinCode.length < 4) {
                    pinCode += number;
                    display.textContent = '*'.repeat(pinCode.length);
                }
            }

            function backspace() {
                pinCode = pinCode.slice(0, -1);
                display.textContent = '*'.repeat(pinCode.length);
            }

            function enter() {
                if (pinCode.length === 4) {
                    $.ajax({
                        url: 'keypad.php',
                        method: 'POST',
                        data: { pin: pinCode },
                        dataType: 'json',
                        success: function (response) {
                            if (response.success) {
                                window.location.href = 'login.php';
                            } else {
                                $('#keypadModal').modal('hide');
                                $('#errorModal').modal('show');
                                pinCode = '';
                                display.textContent = '';
                            }
                        },
                        error: function () {
                            alert('An error occurred. Please try again.');
                        }
                    });
                }
            }

            // Add event listeners for keyboard input when the modal is open
            $('#keypadModal').on('shown.bs.modal', function () {
                $(document).on('keydown.keypad', function (event) {
                    if (event.key >= '0' && event.key <= '9' && pinCode.length < 4) {
                        appendNumber(event.key);
                    } else if (event.key === 'Backspace') {
                        backspace();
                    } else if (event.key === 'Enter') {
                        enter();
                    }
                });
            }).on('hidden.bs.modal', function () {
                $(document).off('keydown.keypad');
                pinCode = '';
                display.textContent = '';
            });


            // document.addEventListener('DOMContentLoaded', function() {
            //     fetch('api.php')
            //         .then(response => response.json())
            //         .then(data => {
            //             const productList = document.getElementById('product-list');
            //             data.forEach(product => {
            //                 const productDiv = document.createElement('div');
            //                 productDiv.className = 'product';
            //                 productDiv.innerHTML = `
            //                     <h2>${product.name}</h2>
            //                     <p id="price-${product.id}">Price: Rp ${product.price}</p>
            //                     <form id="form-${product.id}" onsubmit="handleSubmit(event, ${product.discount}, ${product.id}, '${product.name}', ${product.price})">
            //                         <input type="hidden" name="product_id" value="${product.id}">
            //                         <input type="hidden" name="product_name" value="${product.name}">
            //                         <input type="hidden" name="product_price" value="${product.price}">
            //                         <button type="submit">Buy</button>
            //                     </form>
            //                 `;
            //                 productList.appendChild(productDiv);
            //             });
            //         });
            // });


            function showPaymentModal(id, name, price, discount) {
                createTransaction(id, name, price, discount).then(response => {
                    if (response.success) {
                        // Hapus modal lama jika ada
                        const existingModal = document.getElementById('qrCodeModal');
                        if (existingModal) {
                            existingModal.remove();
                        }
                        // Buat elemen modal baru
                        const modalHTML = `
                            <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Scan QR Code untuk Pembayaran</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="qr-code-container">
                                                <img id="qrCodeImage" src="" alt="QR Code" class="qr-code-image">
                                            </div>
                                            <div id="countdown"></div>
                                            <div class="status-message"></div>
                                            <div class="button-container">
                                                <button type="button" class="btn btn-cancel" id="btn-cancel" onclick="cancelTransaction()">
                                                    Batalkan Transaksi
                                                </button>
                                                <button type="button" class="btn" id="btn-check" onclick="checkPaymentStatus()">
                                                    Cek Status Pembayaran
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        // Tambahkan modal ke body
                        document.body.insertAdjacentHTML('beforeend', modalHTML);
                        
                        // Dapatkan referensi ke modal yang baru dibuat
                        const qrCodeModal = document.getElementById('qrCodeModal');
                        const qrCodeImage = qrCodeModal.querySelector('#qrCodeImage');
                        
                        // Set QR code image
                        qrCodeImage.src = response.qr_code_url;
                        
                        // Set transaction ID
                        qrCodeModal.setAttribute('data-transaction-id', response.order_id);

                        // Start the countdown timer
                        startCountdown(30 * 60); // 30 minutes in seconds

                        // Tampilkan modal
                        const modalInstance = new bootstrap.Modal(qrCodeModal);
                        modalInstance.show();
                    } else {
                        alert('Error: ' + response.message);
                    }
                }).catch(error => {
                    console.error('Error in createTransaction:', error);
                    alert('Terjadi kesalahan saat membuat transaksi.');
                });
            }


            // Add countdown timer function
            function startCountdown(duration) {
                let timer = duration;
                const countdownElement = document.getElementById('countdown');
                let countdown = setInterval(function() {
                    const minutes = parseInt(timer / 60, 10);
                    const seconds = parseInt(timer % 60, 10);

                    countdownElement.textContent = minutes.toString().padStart(2, '0') + ':' + 
                                                seconds.toString().padStart(2, '0');

                    if (--timer < 0) {
                        clearInterval(countdown);
                        const modal = document.getElementById('qrCodeModal');
                        const statusMessage = modal.querySelector('.status-message');
                        statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">QR Code telah kadaluarsa. Silakan lakukan pemesanan ulang.</div>';
                        
                        setTimeout(() => {
                            const qrCodeModal = bootstrap.Modal.getInstance(modal);
                            qrCodeModal.hide();
                        }, 3000);
                    }
                }, 1000);

                // Store the interval ID in the modal element
                const modal = document.getElementById('qrCodeModal');
                modal.setAttribute('data-countdown-id', countdown);

                // Clear the interval when the modal is closed
                modal.addEventListener('hidden.bs.modal', function() {
                    clearInterval(countdown);
                });
            }


            function createTransaction(id, name, price, discount) {
                return fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'create_transaction',
                        product_id: id,
                        product_name: name,
                        product_price: price,
                        discount: discount
                    })
                })
                    .then(response => response.json())
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Terjadi kesalahan saat memproses permintaan.');
                    });
            }


            function checkPaymentStatus() {
                const modal = document.getElementById('qrCodeModal');
                const statusMessage = modal.querySelector('.status-message');
                const checkButton = modal.querySelector('#btn-check');
                const cancelButton = modal.querySelector('#btn-cancel'); // Tambahkan ini

                checkButton.disabled = true;
                checkButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Memeriksa...';
                
                const transactionId = getCurrentTransactionId();
                
                fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'check_payment_status',
                        transaction_id: transactionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    checkButton.disabled = false;
                    checkButton.innerHTML = 'Cek Status Pembayaran';

                    if (data.success) {
                        switch (data.status) {
                            case 'settlement':
                                statusMessage.innerHTML = '<div class="alert alert-success" role="alert">Pembayaran berhasil!</div>';
                                setTimeout(() => {
                                    window.location.href = 'transberhasil.php';
                                }, 1500);
                                break;
                            case 'pending':
                                statusMessage.innerHTML = '<div class="alert alert-warning" role="alert">Pembayaran masih dalam proses. Silakan cek lagi nanti.</div>';
                                break;
                            case 'cancel':
                                statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">Pembayaran dibatalkan.</div>';
                                setTimeout(() => {
                                    window.location.href = 'transbatal.php';
                                }, 1500);
                                break;
                            default:
                                statusMessage.innerHTML = '<div class="alert alert-info" role="alert">Status pembayaran: ' + data.status + '</div>';
                        }
                    } else {
                        statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">Terjadi kesalahan: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    checkButton.disabled = false;
                    checkButton.innerHTML = 'Cek Status Pembayaran';
                    statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">Terjadi kesalahan saat memeriksa status.</div>';
                    console.error('Error:', error);
                });
            }


            // Tambahkan fungsi untuk membatalkan transaksi
            function cancelTransaction() {
                const modal = document.getElementById('qrCodeModal');
                const statusMessage = modal.querySelector('.status-message');
                const cancelButton = modal.querySelector('#btn-cancel');
                const checkButton = modal.querySelector('#btn-check');
                
                cancelButton.disabled = true;
                cancelButton.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Membatalkan...';
                checkButton.disabled = true;
                
                const transactionId = getCurrentTransactionId();
                
                fetch('api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'cancel_transaction',
                        transaction_id: transactionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        statusMessage.innerHTML = '<div class="alert alert-warning" role="alert">Transaksi dibatalkan</div>';
                        setTimeout(() => {
                            window.location.href = 'transbatal.php';
                        }, 1500);
                    } else {
                        cancelButton.disabled = false;
                        cancelButton.innerHTML = 'Batal';
                        checkButton.disabled = false;
                        statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">Gagal membatalkan transaksi: ' + data.message + '</div>';
                    }
                })
                .catch(error => {
                    cancelButton.disabled = false;
                    cancelButton.innerHTML = 'Batal';
                    checkButton.disabled = false;
                    statusMessage.innerHTML = '<div class="alert alert-danger" role="alert">Terjadi kesalahan saat membatalkan transaksi.</div>';
                    console.error('Error:', error);
                });
            }

            // Update modal HTML untuk menambahkan tombol batal
            const modalHTML = `
                <div class="modal fade qr-modal" id="qrCodeModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Scan QR Code untuk Pembayaran</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="qr-code-container">
                                    <img id="qrCodeImage" src="" alt="QR Code" class="qr-code-image">
                                </div>
                                <div id="countdown"></div>
                                <div class="status-message"></div>
                                <div class="button-container">
                                    <button type="button" class="btn btn-cancel" id="btn-cancel" onclick="cancelTransaction()">
                                        Batal
                                    </button>
                                    <button type="button" class="btn" id="btn-check" onclick="checkPaymentStatus()">
                                        Cek Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;



            function getCurrentTransactionId() {
                // Mencari modal QR code
                const modal = document.getElementById('qrCodeModal');

                if (!modal) {
                    console.error('Modal QR code tidak ditemukan');
                    return null;
                }

                // Mencoba mendapatkan ID transaksi dari atribut data
                const transactionId = modal.getAttribute('data-transaction-id');

                if (!transactionId) {
                    console.error('ID transaksi tidak ditemukan pada modal');
                    return null;
                }
                return modal.getAttribute('data-transaction-id');
                return transactionId;
            }


            function updatePrice(productId, originalPrice, discountedPrice) {
                const priceContainer = document.querySelector(`#price-container-${productId}`);
                
                if (discountedPrice < originalPrice) {
                    priceContainer.innerHTML = `
                        <p class="original-price">Rp ${formatNumber(originalPrice)}</p>
                        <p class="discounted-price">Rp ${formatNumber(discountedPrice)}</p>
                    `;
                } else {
                    priceContainer.innerHTML = `
                        <p>Rp ${formatNumber(originalPrice)}</p>
                    `;
                }
            }


            function formatNumber(number) {
                return new Intl.NumberFormat('id-ID').format(number);
            }

            // Event listener untuk form voucher
            document.getElementById('voucher-form').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                fetch('listproduct.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update harga untuk setiap produk
                        data.products.forEach(product => {
                            updatePrice(
                                product.id,
                                product.original_price,
                                product.discounted_price
                            );
                        });
                        
                        // Tampilkan pesan sukses
                        showMessage('Voucher berhasil diterapkan', 'success');
                    } else {
                        // Tampilkan pesan error
                        showMessage(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('Terjadi kesalahan', 'error');
                });
            });


            function showMessage(message, type) {
                const messageContainer = document.getElementById('voucher-message-container');
                messageContainer.innerHTML = `
                    <div class="alert alert-${type === 'success' ? 'success' : 'danger'}">
                        ${message}
                    </div>
                `;
                
                // Hilangkan pesan setelah beberapa detik
                setTimeout(() => {
                    messageContainer.innerHTML = '';
                }, 3000);
            }

        </script>
</body>
</html>