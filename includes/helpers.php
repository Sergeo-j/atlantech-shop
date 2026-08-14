<?php
function clean($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function redirect($path){ header("Location: $path"); exit; }
