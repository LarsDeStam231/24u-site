FROM node:22-bookworm-slim
WORKDIR /app
COPY package.json server.mjs lib-database.mjs lib-mollie.mjs ./
COPY public ./public
RUN mkdir -p /app/data && chown -R node:node /app
USER node
EXPOSE 3000
CMD ["node", "server.mjs"]
