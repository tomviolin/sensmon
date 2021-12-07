
all: build run

build:
	docker build -t sensmon .

run:
	docker kill sensmon || echo ""
	docker rm sensmon || echo ""
	docker run -d --name sensmon --restart always -e "MYSQL_CNF=/home/tomh/microservices/sensmon/secrets/sensmon_citysupply.cnf" sensmon
