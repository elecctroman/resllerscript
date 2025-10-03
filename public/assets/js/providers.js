(function () {
    'use strict';

    var actionsUrl = '/admin/providers/actions.php';

    function createAlert(type, message) {
        var div = document.createElement('div');
        div.className = 'alert alert-' + type + ' mt-3';
        div.setAttribute('role', 'alert');
        div.textContent = message;
        return div;
    }

    function clearAlerts(container) {
        container.querySelectorAll('.alert').forEach(function (alert) {
            alert.remove();
        });
    }

    function handleSettings() {
        var form = document.getElementById('provider-settings-form');
        if (!form) {
            return;
        }

        var statusBadge = document.getElementById('provider-test-status');
        var toggleButton = document.getElementById('provider-key-toggle');
        var keyInput = document.getElementById('provider-api-key');

        if (toggleButton && keyInput) {
            toggleButton.addEventListener('click', function () {
                var visible = toggleButton.getAttribute('data-visible') === '1';
                keyInput.type = visible ? 'password' : 'text';
                toggleButton.textContent = visible ? 'Göster' : 'Gizle';
                toggleButton.setAttribute('data-visible', visible ? '0' : '1');
            });
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearAlerts(form);

            var formData = new FormData(form);
            fetch(actionsUrl, {
                method: 'POST',
                body: formData
            })
                .then(function (response) { return response.json(); })
                .then(function (data) {
                    if (data.ok) {
                        form.appendChild(createAlert('success', data.message || 'Ayarlar kaydedildi.'));
                    } else {
                        form.appendChild(createAlert('danger', data.error || 'Ayarlar kaydedilemedi.'));
                    }
                })
                .catch(function () {
                    form.appendChild(createAlert('danger', 'İstek gönderilirken bir hata oluştu.'));
                });
        });

        var testButton = document.getElementById('provider-test-button');
        if (testButton) {
            testButton.addEventListener('click', function () {
                if (statusBadge) {
                    statusBadge.className = 'badge bg-light text-dark';
                    statusBadge.textContent = 'Test ediliyor...';
                }

                var formData = new FormData(form);
                formData.set('action', 'test_connection');

                fetch(actionsUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        clearAlerts(form);
                        if (data.ok) {
                            if (statusBadge) {
                                statusBadge.className = 'badge bg-success';
                                statusBadge.textContent = 'Bağlı';
                            }
                            var message = 'Bağlantı başarılı.';
                            if (data.credit) {
                                message += ' Sağlayıcı bakiyesi: ' + data.credit;
                            }
                            form.appendChild(createAlert('success', message));
                        } else {
                            if (statusBadge) {
                                statusBadge.className = 'badge bg-danger';
                                statusBadge.textContent = 'Hata';
                            }
                            form.appendChild(createAlert('danger', data.error || 'Bağlantı başarısız.'));
                        }
                    })
                    .catch(function () {
                        if (statusBadge) {
                            statusBadge.className = 'badge bg-danger';
                            statusBadge.textContent = 'Hata';
                        }
                        clearAlerts(form);
                        form.appendChild(createAlert('danger', 'Bağlantı testi sırasında hata oluştu.'));
                    });
            });
        }
    }

    function handleProducts() {
        var table = document.getElementById('lotus-products-table');
        if (!table) {
            return;
        }

        var bootstrapModal = window.bootstrap ? window.bootstrap.Modal : null;
        var singleModalElement = document.getElementById('singleImportModal');
        var singleModal = singleModalElement && bootstrapModal ? new bootstrapModal(singleModalElement) : null;
        var bulkModalElement = document.getElementById('bulkImportModal');
        var bulkModal = bulkModalElement && bootstrapModal ? new bootstrapModal(bulkModalElement) : null;

        var selectAll = document.querySelector('[data-select-all]');
        var bulkButton = document.getElementById('bulk-import-button');
        var singleForm = document.getElementById('single-import-form');
        var bulkForm = document.getElementById('bulk-import-form');

        function getRowProduct(row) {
            try {
                var payload = row.getAttribute('data-product');
                return payload ? JSON.parse(payload) : null;
            } catch (error) {
                return null;
            }
        }

        function refreshBulkButton() {
            var anyChecked = false;
            table.querySelectorAll('input[data-product-select]').forEach(function (checkbox) {
                if (checkbox.checked && !checkbox.disabled) {
                    anyChecked = true;
                }
            });
            if (bulkButton) {
                bulkButton.disabled = !anyChecked;
            }
        }

        table.addEventListener('change', function (event) {
            var target = event.target;
            if (target.matches('input[data-product-select]')) {
                refreshBulkButton();
            }
        });

        if (selectAll) {
            selectAll.addEventListener('change', function () {
                var checked = selectAll.checked;
                table.querySelectorAll('input[data-product-select]').forEach(function (checkbox) {
                    if (!checkbox.disabled) {
                        checkbox.checked = checked;
                    }
                });
                refreshBulkButton();
            });
        }

        table.addEventListener('click', function (event) {
            var button = event.target;
            if (!(button instanceof HTMLElement)) {
                return;
            }

            if (button.dataset.action === 'import-single') {
                event.preventDefault();
                var row = button.closest('tr');
                if (!row) {
                    return;
                }
                var product = getRowProduct(row);
                if (!product || !singleForm) {
                    return;
                }

                singleForm.reset();
                clearAlerts(singleForm);
                document.getElementById('single-lotus-id').value = product.id || '';
                document.getElementById('single-title').value = product.title || '';
                document.getElementById('single-price').value = product.amount || 0;
                document.getElementById('single-description').value = product.content || '';
                document.getElementById('single-snapshot').value = JSON.stringify(product);

                if (singleModal) {
                    singleModal.show();
                }
            }
        });

        if (singleForm) {
            singleForm.addEventListener('submit', function (event) {
                event.preventDefault();
                clearAlerts(singleForm);
                var formData = new FormData(singleForm);

                fetch(actionsUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            var lotusId = formData.get('lotus_product_id');
                            var row = table.querySelector('tr[data-lotus-id="' + lotusId + '"]');
                            if (row) {
                                row.setAttribute('data-imported', '1');
                                row.querySelectorAll('input[data-product-select]').forEach(function (checkbox) {
                                    checkbox.checked = false;
                                    checkbox.disabled = true;
                                });
                                var badge = row.querySelector('span.badge');
                                if (badge && badge.classList.contains('bg-info')) {
                                    badge.textContent = 'Zaten eklendi';
                                } else {
                                    var newBadge = document.createElement('span');
                                    newBadge.className = 'badge bg-info ms-2';
                                    newBadge.textContent = 'Zaten eklendi';
                                    row.querySelector('td:nth-child(2)').appendChild(newBadge);
                                }
                                var actionButton = row.querySelector('[data-action="import-single"]');
                                if (actionButton) {
                                    actionButton.setAttribute('disabled', 'disabled');
                                }
                            }
                            if (singleModal) {
                                singleModal.hide();
                            }
                        } else {
                            singleForm.appendChild(createAlert('danger', data.error || 'Ürün eklenemedi.'));
                        }
                        refreshBulkButton();
                    })
                    .catch(function () {
                        singleForm.appendChild(createAlert('danger', 'İstek gönderilirken bir hata oluştu.'));
                    });
            });
        }

        if (bulkButton && bulkForm) {
            bulkButton.addEventListener('click', function () {
                var selected = [];
                table.querySelectorAll('tr').forEach(function (row) {
                    var checkbox = row.querySelector('input[data-product-select]');
                    if (checkbox && checkbox.checked && !checkbox.disabled) {
                        var product = getRowProduct(row);
                        if (product) {
                            selected.push(product);
                        }
                    }
                });

                if (!selected.length) {
                    return;
                }

                bulkForm.reset();
                clearAlerts(bulkForm);
                document.getElementById('bulk-items').value = JSON.stringify(selected);

                if (bulkModal) {
                    bulkModal.show();
                }
            });

            bulkForm.addEventListener('submit', function (event) {
                event.preventDefault();
                clearAlerts(bulkForm);

                var formData = new FormData(bulkForm);

                fetch(actionsUrl, {
                    method: 'POST',
                    body: formData
                })
                    .then(function (response) { return response.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            var selectedIds = [];
                            try {
                                selectedIds = JSON.parse(formData.get('items')) || [];
                            } catch (error) {
                                selectedIds = [];
                            }

                            selectedIds.forEach(function (item) {
                                var row = table.querySelector('tr[data-lotus-id="' + item.id + '"]');
                                if (row) {
                                    row.setAttribute('data-imported', '1');
                                    var checkbox = row.querySelector('input[data-product-select]');
                                    if (checkbox) {
                                        checkbox.checked = false;
                                        checkbox.disabled = true;
                                    }
                                    var badge = row.querySelector('span.badge');
                                    if (badge && badge.classList.contains('bg-info')) {
                                        badge.textContent = 'Zaten eklendi';
                                    } else {
                                        var newBadge = document.createElement('span');
                                        newBadge.className = 'badge bg-info ms-2';
                                        newBadge.textContent = 'Zaten eklendi';
                                        row.querySelector('td:nth-child(2)').appendChild(newBadge);
                                    }
                                    var actionButton = row.querySelector('[data-action="import-single"]');
                                    if (actionButton) {
                                        actionButton.setAttribute('disabled', 'disabled');
                                    }
                                }
                            });

                            refreshBulkButton();
                            if (bulkModal) {
                                bulkModal.hide();
                            }
                        } else {
                            bulkForm.appendChild(createAlert('danger', data.error || 'Toplu aktarım başarısız.'));
                        }
                    })
                    .catch(function () {
                        bulkForm.appendChild(createAlert('danger', 'Toplu aktarım isteği gönderilemedi.'));
                    });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        handleSettings();
        handleProducts();
    });
})();
