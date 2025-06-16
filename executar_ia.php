<?php
require_once 'includes/db_connect.php';
session_start();
$_SESSION['empresa_id'] = 1;
$empresa_id = $_SESSION['empresa_id'];

// Conta notificações antes
global $conn;
$conn = getConnection();
$stmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$antes = $stmt->fetchColumn();

// Executa a IA
require_once 'IA/ia_regras.php';

// Conta notificações depois
$stmt = $conn->prepare("SELECT COUNT(*) FROM notificacoes WHERE empresa_id = ?");
$stmt->execute([$empresa_id]);
$depois = $stmt->fetchColumn();

$novas = $depois - $antes;

echo "<h2>Execução da IA concluída!</h2>";
echo "<p>Novas notificações geradas: <b>$novas</b></p>";
echo "<a href='notificacoes/notificacoes.php' target='_blank'>Ver JSON de notificações</a>"; 