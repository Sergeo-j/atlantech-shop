        </div><!-- /.container -->
    </main><!-- /.main-content -->
</div><!-- /.layout -->

<script>
(function () {
    var overlay  = document.getElementById('sidebar-overlay');
    var sidebar  = document.querySelector('.sidebar');
    var hamburger = document.getElementById('hamburger-btn');
    var closeBtn = document.getElementById('sidebar-close-btn');

    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.add('active'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.remove('active'); }

    if (hamburger) hamburger.addEventListener('click', openSidebar);
    if (closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if (overlay)   overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
