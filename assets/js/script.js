/**
 * School Management System - Custom JavaScript
 */

const APP_BASE_URL = (function() {
    if (typeof window.APP_URL === 'string' && window.APP_URL.trim() !== '') {
        return window.APP_URL.replace(/\/$/, '');
    }

    const currentScript = document.currentScript;
    if (currentScript && currentScript.src) {
        try {
            return new URL('../../', currentScript.src).href.replace(/\/$/, '');
        } catch (error) {
            // Fall through to the location-based fallback below.
        }
    }

    return window.location.origin.replace(/\/$/, '');
})();

if (typeof window.jQuery === 'function') {
window.jQuery(function($) {

    // Initialize DataTables
    if ($.fn.DataTable) {
        $('.datatable').DataTable({
            "pageLength": 20,
            "ordering": true,
            "searching": true,
            "lengthChange": true,
            "info": true,
            "autoWidth": false,
            "responsive": true,
            "language": {
                "search": "Search:",
                "lengthMenu": "Show _MENU_ entries",
                "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                "paginate": {
                    "first": "First",
                    "last": "Last",
                    "next": "Next",
                    "previous": "Previous"
                }
            }
        });
    }

    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        $('.alert:not(.alert-permanent)').fadeOut('slow');
    }, 5000);

    // Confirm delete
    $('.btn-delete, .delete-btn').on('click', function(e) {
        if (!confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
            e.preventDefault();
            return false;
        }
    });

    // Image upload preview
    $('input[type="file"][accept*="image"]').on('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            const preview = $(this).closest('.form-group').find('.image-preview, .preview-image');

            reader.onload = function(e) {
                if (preview.length) {
                    preview.attr('src', e.target.result).show();
                } else {
                    $(this).after('<img src="' + e.target.result + '" class="image-preview" alt="Preview">');
                }
            }

            reader.readAsDataURL(file);
        }
    });

    // Form validation
    $('form.needs-validation').on('submit', function(e) {
        if (!this.checkValidity()) {
            e.preventDefault();
            e.stopPropagation();
        }
        $(this).addClass('was-validated');
    });

    // Number input validation
    $('input[type="number"]').on('keypress', function(e) {
        const charCode = e.which ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57) && charCode !== 46) {
            e.preventDefault();
            return false;
        }
        return true;
    });

    // Mobile number validation
    $('input[name="contact_no"], input[name="mobile"]').on('keypress', function(e) {
        const charCode = e.which ? e.which : e.keyCode;
        if (charCode > 31 && (charCode < 48 || charCode > 57)) {
            e.preventDefault();
            return false;
        }
    }).on('input', function() {
        if (this.value.length > 10) {
            this.value = this.value.slice(0, 10);
        }
    });

    // Calculate total amount in fee collection
    $('.fee-amount-input').on('input', function() {
        calculateTotal();
    });

    function calculateTotal() {
        let total = 0;
        $('.fee-amount-input').each(function() {
            const value = parseFloat($(this).val()) || 0;
            total += value;
        });
        $('#total_amount').val(total.toFixed(2));
    }

    // Auto-calculate balance
    $('#amount_paid').on('input', function() {
        const totalAmount = parseFloat($('#total_amount').val()) || 0;
        const amountPaid = parseFloat($(this).val()) || 0;
        const balance = totalAmount - amountPaid;
        $('#balance_amount').val(balance.toFixed(2));

        if (balance < 0) {
            $(this).addClass('is-invalid');
        } else {
            $(this).removeClass('is-invalid');
        }
    });

    // Print receipt
    $('.btn-print').on('click', function(e) {
        e.preventDefault();
        window.print();
    });

    // Export to Excel
    $('.btn-export-excel').on('click', function(e) {
        e.preventDefault();
        const table = $(this).data('table') || '.datatable';
        exportTableToExcel(table, 'export.xls');
    });

    function exportTableToExcel(tableId, filename = 'export.xls') {
        const table = document.querySelector(tableId);
        const html = table.outerHTML;
        const url = 'data:application/vnd.ms-excel,' + encodeURIComponent(html);
        const downloadLink = document.createElement('a');
        downloadLink.href = url;
        downloadLink.download = filename;
        downloadLink.click();
    }

    // Loading overlay
    window.showLoading = function() {
        $('body').append('<div class="spinner-overlay"><div class="spinner-border text-light" role="status"></div></div>');
    }

    window.hideLoading = function() {
        $('.spinner-overlay').remove();
    }

    // AJAX form submission
    $('.ajax-form').on('submit', function(e) {
        e.preventDefault();

        const form = $(this);
        const url = form.attr('action');
        const method = form.attr('method') || 'POST';
        const formData = new FormData(this);

        showLoading();

        $.ajax({
            url: url,
            method: method,
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                hideLoading();

                if (response.success) {
                    showAlert('success', response.message);
                    if (response.redirect) {
                        setTimeout(function() {
                            window.location.href = response.redirect;
                        }, 1500);
                    }
                } else {
                    showAlert('danger', response.message);
                }
            },
            error: function(xhr, status, error) {
                hideLoading();
                showAlert('danger', 'An error occurred. Please try again.');
                console.error(error);
            }
        });
    });

    // Show alert
    window.showAlert = function(type, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert">
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        $('.container-fluid').first().prepend(alertHtml);

        setTimeout(function() {
            $('.alert').fadeOut('slow');
        }, 5000);
    }

    // Select all checkboxes
    $('#select-all').on('change', function() {
        $('.select-item').prop('checked', $(this).is(':checked'));
    });

    // Date picker (if needed)
    if ($.fn.datepicker) {
        $('.datepicker').datepicker({
            format: 'dd-mm-yyyy',
            autoclose: true,
            todayHighlight: true
        });
    }

    // Shared student autocomplete
    const studentAutocompleteApi = APP_BASE_URL + '/api/search_students_by_name.php';
    const legacyStudentSearchApi = APP_BASE_URL + '/ajax/search_student.php';

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildStudentMeta(student) {
        const parts = [];
        if (student.admission_no) {
            parts.push('Adm No: ' + student.admission_no);
        }
        const classParts = [student.class_name, student.section_name]
            .filter(function(item) { return item && String(item).trim() !== ''; })
            .join(' ');
        if (classParts) {
            parts.push('Class: ' + classParts);
        }
        if (student.roll_no) {
            parts.push('Roll No: ' + student.roll_no);
        }
        return parts.join(' | ');
    }

    function getAutocompleteValue(student) {
        return student.admission_no || student.student_name || '';
    }

    function getSubmitTarget($input) {
        const explicitTarget = String($input.data('studentAutocompleteSubmit') || '').trim();
        if (explicitTarget) {
            const $target = $(explicitTarget).first();
            if ($target.length) {
                return $target;
            }
        }

        if (String($input.data('studentAutocompleteSkipSubmit') || '').trim() === '1' || String($input.data('studentAutocompleteSkipSubmit') || '').trim().toLowerCase() === 'true') {
            return $();
        }

        const $form = $input.closest('form');
        if (!$form.length) {
            return $();
        }

        return $form.find('button[type="submit"], input[type="submit"]').first();
    }

    function initStudentAutocomplete($input) {
        if (!$input.length || $input.attr('data-student-autocomplete-ready') === '1' || $input.data('studentAutocompleteReady')) {
            return;
        }

        $input.attr('data-student-autocomplete-ready', '1');
        $input.data('studentAutocompleteReady', true);
        $input.attr({
            autocomplete: 'off',
            role: 'combobox',
            'aria-autocomplete': 'list',
            'aria-expanded': 'false'
        });
        $input.addClass('student-autocomplete-input');

        let $host = $input.closest('.input-group');
        if ($host.length) {
            $host.addClass('student-autocomplete-host');
        } else {
            $input.wrap('<div class="student-autocomplete-host"></div>');
            $host = $input.parent();
        }

        const fillField = String($input.data('studentAutocompleteFill') || '').trim().toLowerCase();
        const minLengthRaw = parseInt($input.data('studentAutocompleteMinLength') || '2', 10);
        const minLength = Number.isFinite(minLengthRaw) && minLengthRaw > 0 ? minLengthRaw : 2;
        const idTargetSelector = String($input.data('studentAutocompleteIdTarget') || '').trim();
        let $idTarget = $();
        if (idTargetSelector) {
            $idTarget = $(idTargetSelector).first();
        }

        let $menu = $host.children('.student-autocomplete-menu').first();
        if (!$menu.length) {
            $menu = $('<div>', {
                class: 'student-autocomplete-menu',
                role: 'listbox'
            }).hide();
            $('body').append($menu);
        }

        const menuId = 'student-autocomplete-' + Math.random().toString(36).slice(2, 10);
        $menu.attr('id', menuId);
        $input.attr('aria-controls', menuId);

        let typingTimer = null;
        let xhr = null;

        function positionMenu() {
            if (!$menu.length || !$input.length) {
                return;
            }

            const inputEl = $input.get(0);
            if (!inputEl) {
                return;
            }

            const rect = inputEl.getBoundingClientRect();
            const top = window.scrollY + rect.bottom + 4;
            const left = window.scrollX + rect.left;

            $menu.css({
                position: 'absolute',
                top: top + 'px',
                left: left + 'px',
                width: rect.width + 'px',
                zIndex: 9999
            });
        }

        function hideMenu() {
            $menu.empty().hide();
            $input.attr('aria-expanded', 'false');
        }

        function clearSelectedStudentId() {
            if ($idTarget.length) {
                $idTarget.val('');
                $idTarget.trigger('change');
            }
        }

        function syncSelectedStudentId(student) {
            if (!$idTarget.length) {
                return;
            }

            $idTarget.val(student && student.student_id ? student.student_id : '');
            $idTarget.trigger('change');
        }

        function getSelectedInputValue(student) {
            switch (fillField) {
                case 'student_id':
                    return student.student_id || '';
                case 'student_name':
                    return student.student_name || '';
                case 'roll_no':
                    return student.roll_no || '';
                case 'admission_no':
                    return student.admission_no || '';
                default:
                    return getAutocompleteValue(student);
            }
        }

        function renderMenu(students) {
            $menu.empty();

            students.slice(0, 10).forEach(function(student) {
                const $item = $('<button>', {
                    type: 'button',
                    class: 'student-autocomplete-item',
                    role: 'option'
                });

                $item.append(
                    $('<span>', {
                        class: 'student-autocomplete-name',
                        html: escapeHtml(student.student_name || 'Unnamed Student')
                    })
                );

                $item.append(
                    $('<span>', {
                        class: 'student-autocomplete-meta',
                        html: escapeHtml(buildStudentMeta(student))
                    })
                );

                $item.data('student', student);
                $menu.append($item);
            });

            if ($menu.children().length) {
                positionMenu();
                $menu.show();
                $input.attr('aria-expanded', 'true');
            } else {
                hideMenu();
            }
        }

        function selectStudent(student) {
            $input.val(getSelectedInputValue(student));
            syncSelectedStudentId(student);
            hideMenu();

            const $target = getSubmitTarget($input);
            if ($target.length) {
                window.setTimeout(function() {
                    $target.trigger('click');
                }, 25);
            }
        }

        $input.on('input.studentAutocomplete', function() {
            clearSelectedStudentId();

            const term = $input.val().trim();

            if (typingTimer) {
                clearTimeout(typingTimer);
            }

            if (xhr && xhr.readyState !== 4) {
                xhr.abort();
            }

            if (term.length < minLength) {
                hideMenu();
                return;
            }

            typingTimer = window.setTimeout(function() {
                const currentTerm = $input.val().trim();
                if (currentTerm.length < minLength) {
                    hideMenu();
                    return;
                }

                const classSelector = String($input.data('studentAutocompleteClass') || '').trim();
                const classId = classSelector ? $(classSelector).val() : '';

                xhr = $.ajax({
                    url: studentAutocompleteApi,
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        search: currentTerm,
                        class_id: classId || ''
                    },
                    success: function(response) {
                        if ($input.val().trim() !== currentTerm) {
                            return;
                        }

                        if (response && response.success && Array.isArray(response.students) && response.students.length) {
                            renderMenu(response.students);
                        } else {
                            hideMenu();
                        }
                    },
                    error: function() {
                        if ($input.val().trim().length >= minLength) {
                            hideMenu();
                        }
                    }
                });
            }, 220);
        });

        $input.on('focus.studentAutocomplete', function() {
            if ($menu.children().length) {
                positionMenu();
                $menu.show();
                $input.attr('aria-expanded', 'true');
            }
        });

        $(window).on('scroll.studentAutocomplete resize.studentAutocomplete', function() {
            if ($menu.is(':visible')) {
                positionMenu();
            }
        });

        $input.on('blur.studentAutocomplete', function() {
            window.setTimeout(hideMenu, 150);
        });

        $menu.on('mousedown', '.student-autocomplete-item', function(e) {
            e.preventDefault();
            selectStudent($(this).data('student'));
        });
    }

    $('[data-student-autocomplete="true"]').each(function() {
        initStudentAutocomplete($(this));
    });

    $(document).on('mousedown.studentAutocomplete', function(e) {
        if (!$(e.target).closest('.student-autocomplete-host').length && !$(e.target).closest('.student-autocomplete-menu').length) {
            $('.student-autocomplete-menu').hide();
            $('[data-student-autocomplete="true"]').attr('aria-expanded', 'false');
        }
    });

    // Search student by admission number only when the Search button is clicked
    const $admissionSearch = $('#search_admission_no');
    if ($admissionSearch.length) {
        const $searchTrigger = $admissionSearch
            .closest('.input-group, .form-group, .row, .col, .mb-3')
            .find('button')
            .filter(function() {
                const text = $(this).text().trim().toLowerCase();
                const id = (this.id || '').toLowerCase();
                const title = ($(this).attr('title') || '').toLowerCase();
                return (
                    !id.includes('clear') &&
                    (text === 'search' || text.includes('search') || id.includes('search') || title.includes('search'))
                );
            })
            .first();

        if ($searchTrigger.length) {
            $searchTrigger.on('click', function(e) {
                e.preventDefault();
                const admissionNo = $admissionSearch.val().trim();
                if (admissionNo.length >= 3) {
                    searchStudent(admissionNo);
                }
            });
        }
    }

    function searchStudent(admissionNo) {
        $.ajax({
            url: legacyStudentSearchApi,
            method: 'GET',
            data: { admission_no: admissionNo },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    fillStudentDetails(response.data);
                }
            }
        });
    }

    function fillStudentDetails(student) {
        $('#student_id').val(student.student_id);
        $('#student_name').val(student.student_name);
        $('#class_name').val(student.class_name);
        $('#father_name').val(student.father_name);
        $('#contact_no').val(student.contact_no);
    }

    // Character counter for textareas
    $('textarea[maxlength]').each(function() {
        const maxLength = $(this).attr('maxlength');
        $(this).after('<small class="text-muted char-counter">0 / ' + maxLength + '</small>');
    }).on('input', function() {
        const currentLength = $(this).val().length;
        const maxLength = $(this).attr('maxlength');
        $(this).next('.char-counter').text(currentLength + ' / ' + maxLength);
    });

    // Toggle password visibility
    $('.toggle-password').on('click', function() {
        const input = $($(this).data('target'));
        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            $(this).find('i').removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            $(this).find('i').removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    });
}

(function() {
    const studentAutocompleteApiUrl = APP_BASE_URL + '/api/search_students_by_name.php';

    function escapeHtml(value) {
        return String(value === undefined || value === null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function buildStudentMeta(student) {
        const parts = [];
        if (student.admission_no) {
            parts.push('Adm No: ' + student.admission_no);
        }
        const classParts = [student.class_name, student.section_name]
            .filter(function(item) { return item && String(item).trim() !== ''; })
            .join(' ');
        if (classParts) {
            parts.push('Class: ' + classParts);
        }
        if (student.roll_no) {
            parts.push('Roll No: ' + student.roll_no);
        }
        return parts.join(' | ');
    }

    function getAutocompleteValue(student) {
        return student.admission_no || student.student_name || '';
    }

    function initStudentAutocompleteNative(input) {
        if (!input || input.getAttribute('data-student-autocomplete-ready') === '1') {
            return;
        }

        input.setAttribute('data-student-autocomplete-ready', '1');
        input.classList.add('student-autocomplete-input');
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');

        let host = input.closest('.input-group');
        if (host) {
            host.classList.add('student-autocomplete-host');
        } else {
            const wrapper = document.createElement('div');
            wrapper.className = 'student-autocomplete-host';
            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);
            host = wrapper;
        }

        const fillField = String(input.getAttribute('data-student-autocomplete-fill') || '').trim().toLowerCase();
        const idTargetSelector = String(input.getAttribute('data-student-autocomplete-id-target') || '').trim();
        const classSelector = String(input.getAttribute('data-student-autocomplete-class') || '').trim();
        const minLengthRaw = parseInt(input.getAttribute('data-student-autocomplete-min-length') || '2', 10);
        const minLength = Number.isFinite(minLengthRaw) && minLengthRaw > 0 ? minLengthRaw : 2;
        const idTarget = idTargetSelector ? document.querySelector(idTargetSelector) : null;
        const submitTarget = (function() {
            const explicit = String(input.getAttribute('data-student-autocomplete-submit') || '').trim();
            if (explicit) {
                const target = document.querySelector(explicit);
                if (target) {
                    return target;
                }
            }

            const skipSubmit = String(input.getAttribute('data-student-autocomplete-skip-submit') || '').trim().toLowerCase();
            if (skipSubmit === '1' || skipSubmit === 'true') {
                return null;
            }

            const form = input.closest('form');
            if (!form) {
                return null;
            }

            return form.querySelector('button[type="submit"], input[type="submit"]');
        })();

        let menu = host.querySelector('.student-autocomplete-menu');
        if (!menu) {
            menu = document.createElement('div');
            menu.className = 'student-autocomplete-menu';
            menu.setAttribute('role', 'listbox');
            menu.style.display = 'none';
            document.body.appendChild(menu);
        }

        let typingTimer = null;
        let abortController = null;

        function positionMenu() {
            const rect = input.getBoundingClientRect();
            menu.style.position = 'absolute';
            menu.style.top = (window.scrollY + rect.bottom + 4) + 'px';
            menu.style.left = (window.scrollX + rect.left) + 'px';
            menu.style.width = rect.width + 'px';
            menu.style.zIndex = '9999';
        }

        function hideMenu() {
            menu.innerHTML = '';
            menu.style.display = 'none';
            input.setAttribute('aria-expanded', 'false');
        }

        function clearSelectedStudentId() {
            if (idTarget) {
                idTarget.value = '';
                idTarget.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        function syncSelectedStudentId(student) {
            if (!idTarget) {
                return;
            }

            idTarget.value = student && student.student_id ? student.student_id : '';
            idTarget.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function getSelectedInputValue(student) {
            switch (fillField) {
                case 'student_id':
                    return student.student_id || '';
                case 'student_name':
                    return student.student_name || '';
                case 'roll_no':
                    return student.roll_no || '';
                case 'admission_no':
                    return student.admission_no || '';
                default:
                    return getAutocompleteValue(student);
            }
        }

        function selectStudent(student) {
            input.value = getSelectedInputValue(student);
            syncSelectedStudentId(student);
            hideMenu();

            if (submitTarget) {
                window.setTimeout(function() {
                    submitTarget.click();
                }, 25);
            }
        }

        function renderMenu(students) {
            menu.innerHTML = '';

            students.slice(0, 10).forEach(function(student) {
                const item = document.createElement('button');
                item.type = 'button';
                item.className = 'student-autocomplete-item';
                item.setAttribute('role', 'option');
                item.innerHTML =
                    '<span class="student-autocomplete-name">' + escapeHtml(student.student_name || 'Unnamed Student') + '</span>' +
                    '<span class="student-autocomplete-meta">' + escapeHtml(buildStudentMeta(student)) + '</span>';
                item.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    selectStudent(student);
                });
                menu.appendChild(item);
            });

            if (menu.children.length) {
                positionMenu();
                menu.style.display = 'block';
                input.setAttribute('aria-expanded', 'true');
            } else {
                hideMenu();
            }
        }

        input.addEventListener('input', function() {
            clearSelectedStudentId();

            const term = input.value.trim();
            if (typingTimer) {
                clearTimeout(typingTimer);
            }
            if (abortController) {
                abortController.abort();
            }

            if (term.length < minLength) {
                hideMenu();
                return;
            }

            typingTimer = window.setTimeout(function() {
                const currentTerm = input.value.trim();
                if (currentTerm.length < minLength) {
                    hideMenu();
                    return;
                }

                const classId = classSelector ? (document.querySelector(classSelector)?.value || '') : '';
                abortController = new AbortController();

                fetch(studentAutocompleteApiUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams({
                        search: currentTerm,
                        class_id: classId || ''
                    }),
                    signal: abortController.signal
                })
                    .then(function(response) {
                        if (!response.ok) {
                            throw new Error('Request failed');
                        }
                        return response.json();
                    })
                    .then(function(response) {
                        if (input.value.trim() !== currentTerm) {
                            return;
                        }

                        if (response && response.success && Array.isArray(response.students) && response.students.length) {
                            renderMenu(response.students);
                        } else {
                            hideMenu();
                        }
                    })
                    .catch(function() {
                        if (input.value.trim().length >= minLength) {
                            hideMenu();
                        }
                    });
            }, 220);
        });

        input.addEventListener('focus', function() {
            if (menu.children.length) {
                positionMenu();
                menu.style.display = 'block';
                input.setAttribute('aria-expanded', 'true');
            }
        });

        window.addEventListener('scroll', function() {
            if (menu.style.display !== 'none') {
                positionMenu();
            }
        }, { passive: true });

        window.addEventListener('resize', function() {
            if (menu.style.display !== 'none') {
                positionMenu();
            }
        });

        input.addEventListener('blur', function() {
            window.setTimeout(hideMenu, 150);
        });

        document.addEventListener('mousedown', function(e) {
            if (!host.contains(e.target) && !menu.contains(e.target)) {
                hideMenu();
                input.setAttribute('aria-expanded', 'false');
            }
        });
    }

    function bootstrapStudentAutocompleteFallback() {
        document.querySelectorAll('[data-student-autocomplete="true"]').forEach(function(input) {
            initStudentAutocompleteNative(input);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootstrapStudentAutocompleteFallback);
    } else {
        bootstrapStudentAutocompleteFallback();
    }
})();

// Format currency display
function formatCurrency(amount) {
    return '₹ ' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Validate email
function isValidEmail(email) {
    const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return re.test(email);
}

// Validate mobile
function isValidMobile(mobile) {
    const re = /^[0-9]{10}$/;
    return re.test(mobile);
}
