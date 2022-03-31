
all: build run

MYSQL_CNF = /home/tomh/microservices/sensmon/secrets/sensmon_citysupply.cnf

build:
	docker build -t sensmon .

run:
	docker kill sensmon || echo ""
	docker rm sensmon || echo ""
	docker run -d --name sensmon --restart always -v $(MYSQL_CNF):/etc/my.cnf -e "MYSQL_CNF=$(MYSQL_CNF)" sensmon
