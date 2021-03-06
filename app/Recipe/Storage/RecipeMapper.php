<?php
namespace Recipe\Storage;

/**
 * Recipe Mapper
 */
class RecipeMapper extends DataMapperAbstract
{
    protected $table = 'pp_recipe';
    protected $tableAlias = 'r';
    protected $primaryKey = 'recipe_id';
    protected $modifyColumns = array('title', 'subtitle', 'url', 'servings', 'temperature', 'prep_time', 'prep_time_iso', 'cook_time', 'cook_time_iso', 'ingredients', 'instructions', 'instructions_excerpt', 'notes', 'view_count', 'main_photo', 'published_date');
    protected $domainObjectClass = 'Recipe';
    protected $defaultSelect = 'select SQL_CALC_FOUND_ROWS r.*, concat(u.first_name, \' \', u.last_name) user_name, concat(u.first_name, \'-\', u.last_name) user_url from pp_recipe r join pp_user u on r.created_by = u.user_id';

    /**
     * Get Recipes with Offset
     *
     * Define limit and offset to limit result set.
     * Returns an array of Domain Objects (one for each record)
     * @param int, limit
     * @param int, offset
     * @param bool, only get published recipes (true)
     * @return array
     */
    public function getRecipes($limit = null, $offset = null, $publishedRecipesOnly = true)
    {
        $this->sql = $this->defaultSelect;

        if ($publishedRecipesOnly) {
            $this->sql .= ' where r.published_date <= curdate()';
        }

        // Add order by
        $this->sql .= ' order by r.created_date desc';

        if ($limit) {
            $this->sql .= " limit ?";
            $this->bindValues[] = $limit;
        }

        if ($offset) {
            $this->sql .= " offset ?";
            $this->bindValues[] = $offset;
        }

        return $this->find();
    }

    /**
     * Get Recipes with Photos (first) with Offset
     *
     * Define limit and offset to limit result set.
     * Returns an array of Domain Objects (one for each record)
     * @param int, limit
     * @param int, offset
     * @param bool, only get published recipes (true)
     * @return array
     */
    public function getRecipesWithPhoto($limit = null, $offset = null, $publishedRecipesOnly = true)
    {
        $this->sql = $this->defaultSelect;

        // Add order by clause. MySQL does not have an 'order by nulls last' syntax,
        // so this 'r.main_photo is not null desc' is a trick I found on Stackoverflow to do the same
        // Changed to filter out no-photo recipes
        $this->sql .= ' where r.main_photo is not null';

        if ($publishedRecipesOnly) {
            $this->sql .= ' and r.published_date <= curdate()';
        }

        $this->sql .= ' order by r.view_count desc';

        if ($limit) {
            $this->sql .= " limit ?";
            $this->bindValues[] = $limit;
        }

        if ($offset) {
            $this->sql .= " offset ?";
            $this->bindValues[] = $offset;
        }

        return $this->find();
    }

    /**
     * Get Recipes by Category
     *
     * @param mixed, int or string, category
     * @param int, limit
     * @param int, offset
     * @param bool, only get published recipes (true)
     * @return array
     */
    public function getRecipesByCategory($category, $limit = null, $offset = null, $publishedRecipesOnly = true)
    {
        $this->sql = $this->defaultSelect . " join pp_recipe_category rc on {$this->tableAlias}.recipe_id = rc.recipe_id";

        // Was a category slug or ID passed in?
        if (is_numeric($category)) {
            $where = ' where rc.category_id = ?';
        } else {
            $where = ' join pp_category c on rc.category_id = c.category_id where c.url = ?';
        }

        $this->sql .= $where;
        $this->bindValues[] = $category;

        if ($publishedRecipesOnly) {
            $this->sql .= ' and r.published_date <= curdate()';
        }

        // Add order by
        $this->sql .= ' order by r.created_date desc';

        // Add limit
        if ($limit) {
            $this->sql .= " limit ?";
            $this->bindValues[] = $limit;
        }

        // Add offset
        if ($offset) {
            $this->sql .= " offset ?";
            $this->bindValues[] = $offset;
        }

        return $this->find();
    }

    /**
     * Search Recipes
     *
     * This query searches each of these fields for having all supplied terms:
     *  - Title
     *  - Ingredients
     *  - Instructions
     * @param string, search terms
     * @param int, limit
     * @param int, offset
     * @param bool, only get published recipes (true)
     */
    public function searchRecipes($terms, $limit, $offset, $publishedRecipesOnly = true)
    {
        // Create array of search terms split by word
        $termsArray = preg_split('/\s+/', $terms);
        $termsArray = array_filter($termsArray);

        // Start building SQL statement
        $this->sql = $this->defaultSelect . ' where ';

        // Our search expression. Searches whole words consider proper word boundaries
        $regex = ' REGEXP CONCAT(\'[[:<:]]\', ?, \'e?s?[[:>:]]\')';

        // Start search strings on each field
        $numberOfTerms = count($termsArray) - 1; // Zero indexed
        $titleSearch = '(';
        $ingredientSearch = '(';
        $instructionSearch = '(';

        for ($i = 0; $i <= $numberOfTerms; $i++) {
            $titleSearch .= "r.title {$regex}";
            $ingredientSearch .= "r.ingredients {$regex}";
            $instructionSearch .= "r.instructions {$regex}";

            // Continue search strings with "and" if there is more then one search term
            if ($i !== $numberOfTerms) {
                $titleSearch .= ' and ';
                $ingredientSearch .= ' and ';
                $instructionSearch .= ' and ';
            }
        }

        // Close field search strings
        $titleSearch .= ')';
        $ingredientSearch .= ')';
        $instructionSearch .= ')';

        // Add bind parameters, repeating each set of terms for each field
        for ($i = 0; $i < 3; $i++) {
            foreach ($termsArray as $term) {
                $this->bindValues[] = $term;
            }
        }

        // Add predicates to sql statement
        $this->sql .= " ({$titleSearch} or {$ingredientSearch} or {$instructionSearch})";

        if ($publishedRecipesOnly) {
            $this->sql .= ' and r.published_date <= curdate()';
        }

        if ($limit) {
            $this->sql .= " limit ?";
            $this->bindValues[] = $limit;
        }

        if ($offset) {
            $this->sql .= " offset ?";
            $this->bindValues[] = $offset;
        }

        // Execute
        return $this->find();
    }

    /**
     * Get Recipes by User
     *
     * @param int, user
     * @param int, limit
     * @param int, offset
     * @param bool, only get published recipes (true)
     * @return array
     */
    public function getRecipesByUser($userId, $limit = null, $offset = null, $publishedRecipesOnly = true)
    {
        $this->sql = $this->defaultSelect . ' where r.created_by = ?';
        $this->bindValues[] = $userId;

        if ($publishedRecipesOnly) {
            $this->sql .= ' and r.published_date <= curdate()';
        }

        // Add order by
        $this->sql .= ' order by r.created_date desc';

        // Add limit
        if ($limit) {
            $this->sql .= " limit ?";
            $this->bindValues[] = $limit;
        }

        // Add offset
        if ($offset) {
            $this->sql .= " offset ?";
            $this->bindValues[] = $offset;
        }

        return $this->find();
    }

    /**
     * Get top recipes by view count
     *
     * @param int, limit
     * @return array
     */
    public function getTopRecipes($limit = 5)
    {
        $this->sql = $this->defaultSelect . " where published_date <= curdate() order by r.view_count desc limit ?";
        $this->bindValues[] = $limit;

        return $this->find();
    }

    /**
     * Get a random recipe
     *
     * @param int, limit
     * @return array
     */
    public function getRandomRecipes($limit = 5)
    {
        $this->sql = $this->defaultSelect . " where published_date <= curdate() order by rand() limit ?";
        $this->bindValues[] = $limit;

        return $this->find();
    }

    /**
     * Increment Recipe View Count
     *
     * @param
     */
    public function incrementRecipeViewCount($recipeId)
    {
        $this->sql = 'update pp_recipe set view_count = view_count + 1 where recipe_id = ?;';
        $this->bindValues[] = $recipeId;

        $outcome = $this->execute();
        $this->clear();

        return $outcome;
    }

    /**
     * Save Recipe
     *
     * Adds pre-save manipulation prior to calling _save
     * @param Domain Object
     * @return mixed, Domain Object on success, false otherwise
     */
    public function save(DomainObjectAbstract $recipe)
    {
        // Get dependencies
        $app = \Slim\Slim::getInstance();
        $Toolbox = $app->Toolbox;

        // Set URL safe recipe title
        $recipe->url = $Toolbox->cleanUrl($recipe->title);

        // Set prep time duration in ISO8601 format
        if ($time = $Toolbox->stringToSeconds($recipe->prep_time)) {
            $recipe->prep_time_iso = $Toolbox->timeToIso8601Duration($time);
        }

        // Set cook time duration in ISO8601 format
        if ($time = $Toolbox->stringToSeconds($recipe->cook_time)) {
            $recipe->cook_time_iso = $Toolbox->timeToIso8601Duration($time);
        }

        // Set instructions excerpt
        $recipe->instructions_excerpt = $Toolbox->truncateHtmlText($recipe->instructions);

        return $this->_save($recipe);
    }
}
