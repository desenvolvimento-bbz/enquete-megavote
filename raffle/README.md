# MegaVote - Sistema de Sorteio de Vagas

## Versão 2.0.0 - Modernizada

Sistema eletrônico transparente para sorteio de vagas de garagem, desenvolvido com design moderno baseado na identidade visual da MegaVote.

## 🚀 Características Principais

### ✨ Interface Modernizada
- Design responsivo baseado no site oficial da MegaVote
- Paleta de cores oficial (#60a33d, #166434, #86efac)
- Tipografia moderna com fonte Inter
- Animações e transições suaves
- Cards e componentes visuais modernos

### 🔒 Segurança Aprimorada
- Proteção CSRF em todos os formulários
- Validação robusta de entrada
- Sanitização de dados
- Log de auditoria completo
- Autenticação segura com hash de senhas

### 📊 Funcionalidades Avançadas
- Estatísticas em tempo real
- Exportação aprimorada (PDF e Excel)
- Sistema de vagas fixas persistente
- Configurações flexíveis de sorteio
- Validação de planilhas melhorada

### 🎯 Algoritmo de Sorteio
- Mersenne Twister para aleatoriedade
- Seed de auditoria para verificação
- Suporte a vagas fixas (JSON + planilha)
- Filtros configuráveis (PNE, idosos, casadas)
- Ordenação inteligente dos resultados

## 📋 Requisitos do Sistema

### Servidor Web
- PHP 7.4 ou superior
- Apache/Nginx com mod_rewrite
- Extensões: mbstring, zip, xml, gd

### Dependências PHP (Composer)
```bash
composer require phpoffice/phpspreadsheet
composer require dompdf/dompdf
```

### Estrutura de Diretórios
```
megavote-sorteio-modernizado/
├── assets/
│   ├── css/
│   │   └── megavote-style.css
│   └── js/
│       └── megavote-app.js
├── auth/
│   ├── valida.php
│   └── logout.php
├── data/
│   ├── fixos.json
│   └── actions.log
├── includes/
│   └── footer.php
├── uploads/
│   └── (planilhas enviadas)
├── vendor/
│   └── (dependências Composer)
├── config.php
├── index.php
├── painel.php
├── upload.php
├── sorteio.php
├── fixar_vaga.php
├── limpar.php
├── exportar_pdf.php
├── exportar_xls.php
├── modelo.xlsx
└── README.md
```

## 🔧 Instalação

### 1. Preparação do Ambiente
```bash
# Clone ou extraia os arquivos
cd /var/www/html/megavote-sorteio

# Instale as dependências
composer install

# Configure permissões
chmod 755 data/ uploads/
chmod 644 data/fixos.json
```

### 2. Configuração
Edite o arquivo `config.php`:
```php
// Configurações de produção
define('APP_URL', 'https://seudominio.com');
ini_set('display_errors', 0); // Desabilitar em produção

// Configurações de autenticação
// Altere as senhas padrão!
```

### 3. Usuários Padrão
- **admin@megavote.com.br** / admin123
- **sorteio@megavote.com.br** / sorteio123

⚠️ **IMPORTANTE**: Altere as senhas padrão antes de usar em produção!

## 📄 Formato da Planilha

### Colunas Obrigatórias
| Coluna | Descrição | Exemplo |
|--------|-----------|---------|
| Bloco | Identificação do bloco | A, B, C |
| Apartamento | Número do apartamento | 101, 102, 201 |
| Subsolo | Identificação da vaga | S1, S2, G1 |
| Tipo Vaga | Tipo da vaga | Livre, PNE, Idoso, Casada |
| Apartamento Fixado | Apartamento fixo (opcional) | 101, 205 |

### Exemplo de Dados
```
Bloco | Apartamento | Subsolo | Tipo Vaga | Apartamento Fixado
A     | 101         | S1      | Livre     |
A     | 102         | S2      | PNE       | 102
B     | 201         | G1      | Casada    |
```

## 🎲 Como Usar

### 1. Login
- Acesse o sistema pelo navegador
- Use as credenciais fornecidas
- Será redirecionado para o painel principal

### 2. Importar Planilha
- Clique em "Baixar Modelo de Planilha" para obter o template
- Preencha com os dados das vagas
- Faça upload do arquivo .xlsx

### 3. Configurar Sorteio
- Marque as opções desejadas:
  - Ignorar Vagas PNE
  - Ignorar Vagas Idosos
  - Considerar Vagas Casadas

### 4. Gerenciar Vagas Fixas
- Adicione vagas que devem ser fixadas a apartamentos específicos
- Essas vagas não entrarão no sorteio aleatório

### 5. Realizar Sorteio
- Clique em "Realizar Sorteio"
- Confirme a operação
- Visualize os resultados

### 6. Exportar Relatórios
- PDF: Relatório formatado para impressão
- Excel: Planilha com filtros e formatação

## 🔍 Auditoria e Transparência

### Log de Ações
Todas as ações são registradas em `data/actions.log`:
```
[2024-01-15 14:30:25] Admin (192.168.1.100): Login realizado - Email: admin@megavote.com.br
[2024-01-15 14:31:10] Admin (192.168.1.100): Planilha importada - Arquivo: vagas.xlsx, 50 registros
[2024-01-15 14:32:05] Admin (192.168.1.100): Sorteio iniciado - Configurações: Ignorar PNE
[2024-01-15 14:32:06] Admin (192.168.1.100): Seed do sorteio - Seed: 1705334526
[2024-01-15 14:32:07] Admin (192.168.1.100): Sorteio concluído - Total: 45 vagas, Sorteadas: 40, Fixas: 5, Remanescentes: 5
```

### Verificação de Integridade
- Cada sorteio possui um seed único
- O seed permite reproduzir o sorteio para auditoria
- Timestamp preciso de todas as operações
- Identificação do usuário e IP

## 🎨 Personalização

### Cores do Tema
As cores podem ser alteradas no arquivo `assets/css/megavote-style.css`:
```css
:root {
  --megavote-primary: #60a33d;
  --megavote-primary-dark: #166434;
  --megavote-primary-light: #86efac;
  /* ... outras cores */
}
```

### Logo e Branding
- Substitua o logo no header dos arquivos PHP
- Atualize as informações de contato no footer
- Personalize as mensagens do sistema

## 🔧 Manutenção

### Limpeza de Arquivos
O sistema automaticamente:
- Remove planilhas antigas (mantém últimas 10)
- Rotaciona logs quando necessário
- Limpa sessões expiradas

### Backup
Faça backup regular de:
- `data/fixos.json` (vagas fixas)
- `data/actions.log` (logs de auditoria)
- Banco de dados (se implementado)

### Monitoramento
- Verifique logs de erro do servidor
- Monitore espaço em disco (uploads/)
- Acompanhe logs de auditoria

## 🆘 Solução de Problemas

### Erro de Upload
- Verifique permissões da pasta `uploads/`
- Confirme limite de upload do PHP
- Valide formato da planilha

### Erro de Dependências
```bash
# Reinstalar dependências
composer install --no-dev --optimize-autoloader
```

### Erro de Permissões
```bash
# Corrigir permissões
chmod 755 data/ uploads/
chown www-data:www-data data/ uploads/
```

## 📞 Suporte

Para suporte técnico:
- **Email**: contato@megavote.com.br
- **Site**: https://megavote.com.br
- **Documentação**: Consulte este README

## 📄 Licença

Sistema desenvolvido pela MegaVote. Todos os direitos reservados.

---

**MegaVote** - Tecnologia para Assembleias e Sorteios Eletrônicos

