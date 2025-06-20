# Módulo de Gestão Interativa

Este módulo é responsável pela gestão interativa de pneus e veículos do sistema de frotas.

## Estrutura de Diretórios

```
gestao_interativa/
├── README.md                 # Documentação principal
├── src/                      # Código fonte
│   ├── Controllers/         # Controladores
│   ├── Models/             # Modelos
│   ├── Views/              # Views
│   └── Services/           # Serviços
├── assets/                  # Recursos estáticos
│   ├── css/               # Estilos
│   ├── js/                # Scripts
│   └── img/               # Imagens
├── config/                 # Configurações
├── database/              # Scripts de banco de dados
└── tests/                 # Testes
```

## Plano de Refatoração

### Fase 1: Estruturação
- [ ] Criar estrutura de diretórios
- [ ] Separar código em MVC
- [ ] Configurar autoload
- [ ] Criar classes base

### Fase 2: Backend
- [ ] Criar modelos para Pneus, Veículos e Eixos
- [ ] Implementar serviços de negócio
- [ ] Criar controladores
- [ ] Implementar validações

### Fase 3: Frontend
- [ ] Separar CSS em módulos
- [ ] Modularizar JavaScript
- [ ] Implementar sistema de templates
- [ ] Melhorar interface

### Fase 4: Banco de Dados
- [ ] Criar migrações
- [ ] Implementar seeds
- [ ] Otimizar queries
- [ ] Adicionar índices

### Fase 5: Testes e Documentação
- [ ] Implementar testes unitários
- [ ] Criar testes de integração
- [ ] Documentar código
- [ ] Criar manual do usuário

## Funcionalidades

### Gestão de Pneus
- Visualização interativa
- Alocação de pneus
- Monitoramento de status
- Histórico de alocações
- Rotação de pneus

### Gestão de Veículos
- Visualização de eixos
- Status dos pneus
- Histórico de manutenção
- Alertas e notificações

## Tecnologias Utilizadas
- PHP 7.4+
- MySQL 5.7+
- JavaScript (ES6+)
- CSS3
- HTML5

## Requisitos
- PHP 7.4 ou superior
- MySQL 5.7 ou superior
- Extensões PHP: PDO, JSON, MBString
- Navegador moderno com suporte a ES6

## Instalação
1. Clone o repositório
2. Configure o banco de dados
3. Execute as migrações
4. Configure o servidor web

## Contribuição
1. Fork o projeto
2. Crie uma branch para sua feature
3. Commit suas mudanças
4. Push para a branch
5. Crie um Pull Request 