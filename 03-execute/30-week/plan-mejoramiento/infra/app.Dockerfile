# App web de Robotics (T-20). Contexto de construcción: la carpeta app/ (ver compose.yaml).

# 1. Compilar con Node. La dirección de la API queda fija en el JavaScript al compilar.
FROM node:24-alpine AS build
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY . .
ARG VITE_API_URL=http://localhost:8000/api/v1
ENV VITE_API_URL=$VITE_API_URL
RUN npm run build

# 2. Servir los archivos estáticos con nginx: la imagen final no lleva Node ni el código fuente
FROM nginx:1.27-alpine
COPY --from=build /app/dist /usr/share/nginx/html
EXPOSE 80
