proxy_http_version 1.1;
proxy_set_header Host $host;
proxy_set_header X-Real-IP $remote_addr;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Prefix /multifuturo;

# Upgrade para websockets (Vite HMR, se for usado através do proxy).
proxy_set_header Upgrade $http_upgrade;
proxy_set_header Connection $connection_upgrade;

# O "artisan serve" é um servidor de desenvolvimento: dá-lhe tempo.
proxy_connect_timeout 10s;
proxy_read_timeout 120s;
proxy_buffering off;
