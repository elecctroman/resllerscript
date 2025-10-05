(function ($) {
    'use strict';

    const endpoints = {
        login: '/epin_client/api/login.php',
        register: '/epin_client/api/register.php',
        logout: '/epin_client/api/logout.php',
        products: '/epin_client/api/get_products.php',
        orderCreate: '/epin_client/api/order_create.php',
        orders: '/epin_client/api/get_orders.php',
        stats: '/epin_client/api/dashboard_stats.php',
        balance: '/epin_client/api/get_balance.php',
        payment: '/epin_client/api/add_balance.php',
        tickets: '/epin_client/api/get_tickets.php',
        ticketCreate: '/epin_client/api/create_ticket.php',
        profileUpdate: '/epin_client/api/update_profile.php',
        passwordUpdate: '/epin_client/api/update_password.php'
    };

    function handleError($el, message) {
        $el.text(message).removeClass('d-none');
    }

    function clearAlert($el) {
        $el.addClass('d-none').text('');
    }

    function fetchProducts(targetSelector, limit) {
        const $container = $(targetSelector);
        if (!$container.length) return;
        $.getJSON(endpoints.products, { limit: limit || '' })
            .done(function (response) {
                $container.empty();
                if (!response.products || !response.products.length) {
                    $container.append('<div class="col-12 text-center text-muted">Ürün bulunamadı.</div>');
                    return;
                }
                response.products.forEach(function (product) {
                    const disabled = product.stock <= 0 ? 'disabled' : '';
                    const card = `
                        <div class="col-md-4">
                            <div class="card h-100 shadow-sm">
                                <div class="card-body d-flex flex-column">
                                    <h5 class="card-title mb-2">${product.name}</h5>
                                    <p class="text-muted flex-grow-1">${product.description || ''}</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-success">₺${product.price}</span>
                                            <span class="badge bg-secondary">Stok: ${product.stock}</span>
                                        </div>
                                        <button class="btn btn-primary btn-sm buy-product" data-id="${product.id}" ${disabled}>
                                            <i class="fa-solid fa-cart-plus me-1"></i>Satın Al
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>`;
                    $container.append(card);
                });
            })
            .fail(function () {
                $container.html('<div class="col-12 text-center text-danger">Ürünler yüklenirken hata oluştu.</div>');
            });
    }

    function fetchOrders() {
        const $tableBody = $('#orders-table tbody');
        if (!$tableBody.length) return;
        $.getJSON(endpoints.orders)
            .done(function (response) {
                $tableBody.empty();
                if (!response.orders || !response.orders.length) {
                    $('#orders-empty').removeClass('d-none');
                    return;
                }
                $('#orders-empty').addClass('d-none');
                response.orders.forEach(function (order) {
                    const row = `
                        <tr>
                            <td>${order.id}</td>
                            <td>${order.product_name}</td>
                            <td>${order.created_at}</td>
                            <td><span class="badge bg-${order.status === 'completed' ? 'success' : 'secondary'}">${order.status_label}</span></td>
                            <td><code>${order.pin_code || '-'}</code></td>
                        </tr>`;
                    $tableBody.append(row);
                });
            })
            .fail(function () {
                $('#orders-empty').text('Siparişler yüklenemedi.');
            });
    }

    function fetchStats() {
        if (!$('#total-orders').length) return;
        $.getJSON(endpoints.stats)
            .done(function (response) {
                if (typeof response.balance !== 'undefined') {
                    $('#balance-amount').text('₺' + response.balance);
                }
                $('#total-orders').text(response.order_count || 0);
                $('#open-tickets').text(response.open_tickets || 0);
                if (response.quick_products) {
                    const $quick = $('#quick-products').empty();
                    response.quick_products.forEach(function (product) {
                        const disabled = product.stock <= 0 ? 'disabled' : '';
                        const card = `
                            <div class="col-md-4">
                                <div class="card h-100">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">${product.name}</h5>
                                        <p class="text-muted flex-grow-1">${product.description || ''}</p>
                                        <div class="d-flex justify-content-between align-items-center mt-2">
                                            <span class="badge bg-success">₺${product.price}</span>
                                            <button class="btn btn-primary btn-sm buy-product" data-id="${product.id}" ${disabled}>Satın Al</button>
                                        </div>
                                    </div>
                                </div>
                            </div>`;
                        $quick.append(card);
                    });
                }
            });
    }

    function fetchTickets() {
        const $list = $('#ticket-list');
        if (!$list.length) return;
        $.getJSON(endpoints.tickets)
            .done(function (response) {
                $list.empty();
                if (!response.tickets || !response.tickets.length) {
                    $('#tickets-empty').removeClass('d-none');
                    return;
                }
                $('#tickets-empty').addClass('d-none');
                response.tickets.forEach(function (ticket) {
                    const adminReply = ticket.admin_response ? `<div class="mt-2 p-3 bg-light border rounded"><strong>Yanıt:</strong><br>${ticket.admin_response}</div>` : '';
                    const item = `
                        <div class="list-group-item">
                            <div class="d-flex justify-content-between">
                                <h6 class="mb-1">${ticket.subject}</h6>
                                <span class="ticket-status badge bg-${ticket.status === 'closed' ? 'secondary' : 'warning'}">${ticket.status_label}</span>
                            </div>
                            <div class="text-muted small">${ticket.created_at}</div>
                            <p class="mb-2 mt-2">${ticket.message}</p>
                            ${adminReply}
                        </div>`;
                    $list.append(item);
                });
            })
            .fail(function () {
                $('#tickets-empty').text('Ticketlar yüklenemedi.');
            });
    }

    function showModal(title, body) {
        let $modal = $('#globalModal');
        if (!$modal.length) {
            const modalHtml = `
                <div class="modal fade" id="globalModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title"></h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                            </div>
                            <div class="modal-body"></div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Tamam</button>
                            </div>
                        </div>
                    </div>
                </div>`;
            $('body').append(modalHtml);
            $modal = $('#globalModal');
        }
        $modal.find('.modal-title').html(title);
        $modal.find('.modal-body').html(body);
        const modal = new bootstrap.Modal($modal[0]);
        modal.show();
    }

    $(document).on('click', '.buy-product', function () {
        const productId = $(this).data('id');
        const $button = $(this);
        $button.prop('disabled', true);
        $.ajax({
            url: endpoints.orderCreate,
            method: 'POST',
            data: {
                product_id: productId,
                csrf_token: CSRF_TOKEN
            },
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                showModal('Sipariş Oluşturuldu', `<p class="mb-2">Siparişiniz başarıyla oluşturuldu.</p><p class="mb-0"><strong>E-PIN:</strong> <code>${response.pin_code}</code></p>`);
                fetchOrders();
                fetchStats();
            } else {
                showModal('İşlem Başarısız', `<p>${response.message}</p>`);
            }
        }).fail(function () {
            showModal('Hata', '<p>İşlem sırasında bir hata oluştu.</p>');
        }).always(function () {
            $button.prop('disabled', false);
        });
    });

    $('#refresh-products').on('click', function () {
        fetchProducts('#product-list');
    });

    $('#quick-refresh').on('click', function () {
        fetchStats();
    });

    $('#orders-refresh').on('click', function () {
        fetchOrders();
    });

    $('#tickets-refresh').on('click', function () {
        fetchTickets();
    });

    $('#logout-link').on('click', function (e) {
        e.preventDefault();
        $.post(endpoints.logout, { csrf_token: CSRF_TOKEN }, function () {
            window.location.href = '/epin_client/public/index.php';
        }, 'json');
    });

    $('#login-form').on('submit', function (e) {
        e.preventDefault();
        const $error = $('#login-error');
        clearAlert($error);
        $.ajax({
            url: endpoints.login,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.location.href = '/epin_client/public/dashboard.php';
            } else {
                handleError($error, response.message || 'Giriş başarısız.');
            }
        }).fail(function () {
            handleError($error, 'Giriş sırasında hata oluştu.');
        });
    });

    $('#register-form').on('submit', function (e) {
        e.preventDefault();
        const $error = $('#register-error');
        clearAlert($error);
        const password = $('#register-password').val();
        const confirm = $('#register-password-confirm').val();
        if (password !== confirm) {
            return handleError($error, 'Şifreler eşleşmiyor.');
        }
        $.ajax({
            url: endpoints.register,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                window.location.href = '/epin_client/public/dashboard.php';
            } else {
                handleError($error, response.message || 'Kayıt başarısız.');
            }
        }).fail(function () {
            handleError($error, 'Kayıt sırasında hata oluştu.');
        });
    });

    $('#payment-form').on('submit', function (e) {
        e.preventDefault();
        const $error = $('#payment-error');
        const $success = $('#payment-success');
        clearAlert($error);
        clearAlert($success);
        $.ajax({
            url: endpoints.payment,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $success.text(response.message).removeClass('d-none');
                $('#amount').val('');
                fetchStats();
            } else {
                handleError($error, response.message || 'İşlem başarısız.');
            }
        }).fail(function () {
            handleError($error, 'Talebiniz alınamadı.');
        });
    });

    $('#ticket-form').on('submit', function (e) {
        e.preventDefault();
        const $error = $('#ticket-error');
        const $success = $('#ticket-success');
        clearAlert($error);
        clearAlert($success);
        $.ajax({
            url: endpoints.ticketCreate,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $success.text('Ticket başarıyla oluşturuldu.').removeClass('d-none');
                $('#ticket-form')[0].reset();
                fetchTickets();
            } else {
                handleError($error, response.message || 'Ticket oluşturulamadı.');
            }
        }).fail(function () {
            handleError($error, 'Ticket oluşturulurken hata oluştu.');
        });
    });

    $('#profile-form').on('submit', function (e) {
        e.preventDefault();
        const $success = $('#profile-success');
        const $error = $('#profile-error');
        clearAlert($success);
        clearAlert($error);
        $.ajax({
            url: endpoints.profileUpdate,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $success.text('Profil bilgileriniz güncellendi.').removeClass('d-none');
            } else {
                handleError($error, response.message || 'Profil güncellenemedi.');
            }
        }).fail(function () {
            handleError($error, 'Profil güncelleme sırasında hata oluştu.');
        });
    });

    $('#password-form').on('submit', function (e) {
        e.preventDefault();
        const $success = $('#password-success');
        const $error = $('#password-error');
        clearAlert($success);
        clearAlert($error);
        const newPassword = $('#new-password').val();
        const confirm = $('#new-password-confirm').val();
        if (newPassword !== confirm) {
            return handleError($error, 'Yeni şifreler eşleşmiyor.');
        }
        $.ajax({
            url: endpoints.passwordUpdate,
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json'
        }).done(function (response) {
            if (response.success) {
                $success.text('Şifreniz güncellendi.').removeClass('d-none');
                $('#password-form')[0].reset();
            } else {
                handleError($error, response.message || 'Şifre güncellenemedi.');
            }
        }).fail(function () {
            handleError($error, 'Şifre güncelleme sırasında hata oluştu.');
        });
    });

    // Initial loads
    fetchProducts('#product-list');
    fetchStats();
    fetchOrders();
    fetchTickets();
})(jQuery);
