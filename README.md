# Symfony-API_Builder
### DTO and Service file creator for build APIs faster.
This package contains a command that generates dedicated DTO and Service templates, callable from your API endpoints. You can override request parameters or filter outputs directly from controllers without worrying about business logic involving CRUD, pagination, or standard filters like search and between. You can customize the validator directly in the Entity file to validate data before persistence.

## Build DTO and Service by Class Entity
```bash
php bin/console app:build-api Person
```

## Controller example

```php

#[Route('/api/person', name: 'app_person')]
class PersonController extends AbstractController
{

    public function __construct
    (
        PersonService $personService
    )
    {
        $this->personService = $personService;
    }
    #[Route(name: 'app_person_save', methods: ['POST', 'GET'])]
    public function endpoint1(Request $request): JsonResponse
    {
        $method= $request->getMethod();
        if($method == 'POST')
        {
            return $this->json($this->personService->save($request));
        }
        //Overwrite a request to force the result
        //$request->request->set('filter', ["id" => 1]);
        return $this->json($this->personService->fetchAll($request));
    }

    #[Route('/{id}', name: 'app_person_save2', methods: ['GET', 'DELETE'])]
    public function endpoint2(Request $request, $id): JsonResponse
    {
        $method= $request->getMethod();
        if($method == 'DELETE')
        {
            return $this->json($this->personService->delete($id));
        }
        return $this->json($this->personService->fetch($id));
    }
}

```

### Request example
```
/api/person?filter[name]=Bob
```
```
/api/person?filter[job][role]=FrontEnd Developer
```
```
/api/person?search[name,surname]=Ross
```
```
/api/person?between[birthday]=1990-01-01AND1992-01-01
```

## Validator
```php
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonRepository::class)]
class Person
{
    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    private ?string $name = null;
}
```
