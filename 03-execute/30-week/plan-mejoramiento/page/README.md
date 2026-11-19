# Page — Robotics

Sitio público estático que presenta el producto (plan D-14): **HTML y CSS puros**, sin compilar, sin librerías y **sin la API**.

| Archivo | Qué es |
|---|---|
| `index.html` | Contenido: qué hace, cómo funciona, roles, reglas del préstamo y preguntas frecuentes |
| `styles.css` | Diseño adaptable (móvil, tableta, escritorio) y modo oscuro automático |

## Abrir

Doble clic en `index.html`, o desde una terminal en esta carpeta:

```powershell
start index.html
```

## Guion de recorrido (evidencia de T-23)

| # | Paso | Resultado esperado |
|---|---|---|
| 1 | Con la API y Docker **apagados**, abrir `index.html` | La página carga completa: no depende de ningún servidor |
| 2 | Ver en escritorio (ventana ancha) | Menú arriba en una línea; «Qué hace» y «Cómo funciona» en 4 columnas; roles en 3 |
| 3 | Achicar la ventana a ancho de celular, o F12 → modo dispositivo → iPhone | Todo en una columna, sin barra horizontal; el texto se lee sin hacer zoom |
| 4 | Clic en «Reglas» en el menú | Baja a las cuatro reglas: 12 días, máximo 2, sin vencidos, sin repetir |
| 5 | Clic en una pregunta frecuente | Se despliega la respuesta; otro clic la cierra |
| 6 | Pulsar Tab desde el inicio | Primero aparece «Saltar al contenido»; cada enlace y pregunta muestra un borde verde al tener el foco |
| 7 | Activar el modo oscuro del sistema operativo | La página cambia a fondo oscuro con buen contraste |
