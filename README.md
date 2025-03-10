# Symfony-API_Builder
### DTO and Service file creator for build APIs faster.
This package contains a command that generates dedicated DTO and Service templates, callable from your API endpoints. You can override request parameters or filter outputs directly from controllers without worrying about business logic involving CRUD, pagination, or standard filters like search and between. You can customize the validator directly in the Entity file to validate data before persistence.

## Controller example

'''php
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
'''
