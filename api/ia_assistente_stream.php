<?php
/**
 * SSE — streaming de fases da consulta ao Copiloto.
 */
define('SF_IA_SSE', true);

$_GET['action'] = $_GET['action'] ?? 'pergunta';

require __DIR__ . '/ia_assistente.php';
