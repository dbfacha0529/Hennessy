<?php
session_start();

// 配列の中身を確認
echo '<pre>';
print_r($_SESSION["RESERVE_DATA"]);
echo '</pre>';