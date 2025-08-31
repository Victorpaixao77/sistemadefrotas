import re
import sys

def limpar_sql(arquivo_entrada, arquivo_saida):
    with open(arquivo_entrada, 'r', encoding='utf-8') as f:
        conteudo = f.read()
    
    # Remove espaços extras dos valores entre aspas simples
    # Padrão: encontra ' seguido de qualquer coisa, seguido de ' e remove espaços internos
    conteudo_limpo = re.sub(
        r"'([^']*)'",
        lambda m: f"'{m.group(1).strip()}'",
        conteudo
    )
    
    with open(arquivo_saida, 'w', encoding='utf-8') as f:
        f.write(conteudo_limpo)
    
    print(f"Arquivo processado com sucesso!")
    print(f"Entrada: {arquivo_entrada}")
    print(f"Saída: {arquivo_saida}")

if __name__ == "__main__":
    if len(sys.argv) != 3:
        print("Uso: python limpar_sql.py arquivo_entrada.sql arquivo_saida.sql")
        print("Exemplo: python limpar_sql.py sql.sql sql_formatado.sql")
    else:
        limpar_sql(sys.argv[1], sys.argv[2]) 