    </div>
</main>
<footer class="bg-dark text-white py-4 mt-auto">
    <div class="container text-center small">
        &copy; <?= date('Y') ?> E-PIN Market - Tüm hakları saklıdır.
    </div>
</footer>
<script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-3fpdpA6arZz+Y3jACpShyp66gDJZH81PoYjF0A0Jw2E=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>const CSRF_TOKEN = '<?= csrf_token() ?>';</script>
<script src="/epin_client/assets/js/app.js"></script>
</body>
</html>
