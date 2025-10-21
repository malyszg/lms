<tech-stack>
Frontend - PHP symfony:
- php symfony szybkie tworzenie kontrolerów
- php symfony ma komponent do tworzenia autoryzacji użytkownika
- możliwość tworzenia komponentów interfejsowych w twig-u
- HTMX + Bootstrap do prostego interfejsu

Backend - php symfony MYSQL rabbit
- łatwa komunikacja z bazą danych poprzez doctrine z MYSQL
- obiektowe programowanie pozwala na tworzenie interfejsów
- redis do cache-owania
- mozliwość szybkiego tworzenia API poprzez kontrolery
- rabbit do przyjmowania leadow i rozsylania ich dalej
- Guzzle do komunikacji z API

AI - Komunikacja z modelamem przez usługę Google Gemini

Testowanie:
- PHPUnit 10.5 do testów jednostkowych i integracyjnych
- Symfony PHPUnit Bridge do testów funkcjonalnych
- Symfony WebTestCase do testów E2E API
- BrowserKit do symulacji przeglądarki
- Doctrine DataFixtures do zarządzania danymi testowymi
- Apache JMeter / k6.io do testów wydajnościowych
- OWASP ZAP do testów bezpieczeństwa
- Xdebug do analizy pokrycia kodu testami
- Prophecy/Mockery do mockowania zależności

CI/CD i Hosting:
- Github Actions do tworzenia pipeline'ów CI/CD
- DigitalOcean do hostowania aplikacji za pośrednictwem obrazu docker
</tech-stack>

Dokonaj krytycznej lecz rzeczowej analizy czy <tech-stack> odpowiednio adresuje potrzeby @prd.md. Rozważ następujące pytania:
1. Czy technologia pozwoli nam szybko dostarczyć MVP?
2. Czy rozwiązanie będzie skalowalne w miarę wzrostu projektu?
3. Czy koszt utrzymania i rozwoju będzie akceptowalny?
4. Czy potrzebujemy aż tak złożonego rozwiązania?
5. Czy nie istnieje prostsze podejście, które spełni nasze wymagania?
6. Czy technologie pozwoli nam zadbać o odpowiednie bezpieczeństwo?