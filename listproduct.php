<?php
require 'function.php';
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
</head>
<body>
    <div class="container-index">
        <div class="header-index">
            <div class="container-button">
                <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#keypadModal"
                    style="position: absolute; right: 30px; top: 30px; background: none; border: none;">
                    <i class="fas fa-lock" style="font-size: 20px; color: rgba(0, 0, 0, 0.2);"></i>
                </button>
            </div>
            <div class="content">
                <div class="product-list" id="product-list">
                    <?php foreach ($products as $product): ?>
                        <div class="product">
                            <h2><?php echo htmlspecialchars($product['name']); ?></h2>
                            <p id="price-<?php echo $product['id']; ?>">Rp
                                <?php echo number_format($product['price'], 0, ',', '.'); ?>
                            </p>
                            <p id="description-<?php echo $product['id']; ?>">
                                <?php echo htmlspecialchars($product['description']); ?>
                            </p>
                            <button
                                onclick="showPaymentModal(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars($product['name']); ?>', <?php echo $product['price']; ?>, <?php echo $product['discount']; ?>)">Buy</button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="container-qrcode" style="display: contents;">
                    <div id="qrcode" class="qrcode"></div>
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
                                        <div class="qr-code-container">
                                            <img src="${response.qr_code_url}" alt="QR Code" class="qr-code-image">
                                        </div>
                                        <p class="qr-instructions">*scan QR code ini untuk melakukan pembayaran</p>
                                        <div style="display: flex; justify-content: center;">
                                            <button type="button" class="btn btn-cancel" data-bs-dismiss="modal">Batal</button>
                                            <button type="button" onclick="checkPaymentStatus()"  class="btn" id="btn-check">cek</button>
                                            <button type="button"   class="btn btn-check" >cek</button>
                                        </div>
                                        <div class="status-message mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        `

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