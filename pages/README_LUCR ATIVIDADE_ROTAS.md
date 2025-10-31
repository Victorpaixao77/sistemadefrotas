# 📊 Análise de Lucratividade de Rotas

## ✅ Implementação Concluída

Foi adicionada uma **seção completa de análise de lucratividade** no modal "Detalhes da Rota".

---

## 🎯 O que foi adicionado:

### 1. **Visual Moderno e Profissional**
- 4 cards coloridos com gradientes mostrando:
  - 💰 **Receita Bruta** (Frete)
  - 📊 **Lucro Bruto** (Frete - Comissão)
  - 💚 **Lucro Líquido** (Lucro Final)
  - 📈 **Margem Líquida** (% sobre Receita)

### 2. **Tabela Detalhada**
Mostra a composição completa do resultado:
```
+ Receita Bruta (Frete)           R$ 10.000,00   100%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
- Comissão Motorista              R$  1.500,00    15%
- Despesas de Viagem              R$    800,00     8%
- Abastecimentos                  R$  1.200,00    12%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
📊 Lucro Bruto                    R$  8.500,00    85%
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
✅ Lucro Líquido                  R$  6.500,00    65%
```

### 3. **Indicador Visual de Rentabilidade**
Barra de progresso colorida que mostra:
- ❌ Prejuízo (vermelho)
- ⚠️ Margem Baixa (amarelo)
- ✅ Margem Boa (azul claro)
- 🎯 Margem Excelente (azul)

---

## 🔧 Como funciona:

### **Arquivos criados/modificados:**

1. **`pages/routes.php`**
   - Adicionada seção HTML "Análise de Lucratividade" no modal
   - Script observer que detecta quando o modal é aberto
   - Atributo `data-route-id` no modal

2. **`js/route-profitability.js`** (NOVO)
   - Função `calcularLucratividade(rotaId)`
   - Busca dados da rota via API
   - Calcula: Receita, Lucro Bruto, Lucro Líquido, Margem
   - Atualiza todos os campos do modal

---

## 📱 Como usar:

1. **Abra a página de rotas:**
   ```
   http://localhost/sistema-frotas/pages/routes.php
   ```

2. **Clique no ícone 👁️ (visualizar)** de qualquer rota

3. **Role até o final do modal**
   - Verá a seção "📊 Análise de Lucratividade"
   - Os valores serão calculados automaticamente

4. **Dados exibidos:**
   - Receita Bruta (valor do frete)
   - Lucro Bruto (frete - comissão)
   - Lucro Líquido (após todas deduções)
   - Margem Líquida (%)
   - Tabela detalhada
   - Indicador visual de rentabilidade

---

## 🔍 Fórmulas de Cálculo:

```javascript
Receita Bruta = Frete

Lucro Bruto = Frete - Comissão

Lucro Líquido = Frete - Comissão - Despesas de Viagem - Abastecimentos

Margem Líquida = (Lucro Líquido / Receita Bruta) × 100
```

---

## ⚙️ Integração Automática:

O sistema usa **MutationObserver** para detectar quando:
1. O modal é aberto (`display: block`)
2. O `data-route-id` é definido

Quando ambos ocorrem, chama automaticamente:
```javascript
calcularLucratividade(rotaId);
```

---

## 🎨 Cores e Temas:

- **Receita**: Azul (`#4facfe → #00f2fe`)
- **Lucro Bruto**: Rosa/Amarelo (`#fa709a → #fee140`)
- **Lucro Líquido**: Verde (`#43e97b → #38f9d7`)
- **Margem**: Roxo (`#667eea → #764ba2`)

---

## 🐛 Debug:

Se não aparecer a lucratividade:

1. **Abra o Console (F12)**
2. **Verifique logs:**
   ```
   Calculando lucratividade para rota: 123
   ```

3. **Certifique-se que:**
   - O modal tem `id="viewRouteModal"`
   - O atributo `data-route-id` é definido ao abrir
   - O arquivo `js/route-profitability.js` está carregando

---

## ✅ Pronto para usar!

A análise de lucratividade agora está completamente integrada e funcionando! 🎉

