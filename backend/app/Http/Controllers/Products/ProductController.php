<?php

namespace App\Http\Controllers\Products;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductDetailResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductUserRating;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;



class ProductController extends Controller
{
    /**
     * Display a listing of the products.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $products = Product::all();

        return response()->json([
            'status' => true,
            'data' => ProductResource::collection($products),
        ]);
    }

    /**
     * Store a newly created product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */

     public function store(Request $request)
     {
         try {
             // Validate incoming request
             $validator = Validator::make($request->all(), [
                 'name' => 'required|string|max:255',
                 'description' => 'nullable|string',
                 'price' => 'required|numeric',
                 'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:204800', // Adjust max file size as needed
                 'category_id' => 'required|exists:categories,id',
             ]);
 
             // Handle validation errors
             if ($validator->fails()) {
                 return response()->json([
                     'status' => false,
                     'message' => 'Validation error',
                     'errors' => $validator->errors()
                 ], 422);
             }
 
             // Handle image upload if provided
             $imageName = null;
             if ($request->hasFile('image')) {
                 $image = $request->file('image');
                 $imageName = time() . '_' . $image->getClientOriginalName();
                 $image->move(public_path('/api/products/image/'), $imageName);
                  // Prepare the response with correct image URL if an image was uploaded
                 $image_url = $imageName ? asset('/api/products/image/' . $imageName) : null;
             }
            
             // Get the authenticated user
             $user_id = Auth::id();
             // return $user_id;
          
             // Create new product instance and associate with the user
             $product = new Product();
             $product->name = $request->name;
             $product->description = $request->description;
             $product->price = $request->price;
             $product->image = $imageName;
             $product->category_id = $request->category_id;
             $product->user_id = $user_id;
             $product->save();
          
            
 
             // Return success response
             return response()->json([
                 'status' => true,
                 'data' => $product, // Assuming you're returning the product directly
                 'message' => 'Product created successfully'
             ], 201);
 
         } catch (Exception $error) {
             // Return error response if an exception occurs
             return response()->json([
                 'status' => false,
                 'message' => 'Product creation failed',
                 'error' => $error->getMessage()
             ], 400);
         }
     }
     


    /**
     * Display the specified product.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, $id)
    {
        try {
            $product = Product::findOrFail($id); // Ensure product with ID exists
            return response()->json([
                'status' => true,
                'data' => new ProductDetailResource($product),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Product not found or an error occurred.',
            ], 404); // Return appropriate HTTP status code
        }
    }

    /**
     * Update the specified product in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Product $product)
    {
        try {
            // Validate incoming request
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'price' => 'required|numeric',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:204800', // Adjust max file size as needed
                'category_id' => 'required|exists:categories,id',
            ]);

            // Handle validation errors
            if ($validator->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Handle image update if provided
            if ($request->hasFile('image')) {
                // Delete previous image if exists
                if ($product->image) {
                    Storage::delete('public/products/' . $product->image);
                }

                // Upload new image
                $file = $request->file('image');
                $fileName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
                $extension = $file->getClientOriginalExtension();
                $fileNameToStore = $fileName . '_' . time() . '.' . $extension;
                $file->storeAs('public/products', $fileNameToStore); // Store in storage/app/public/products

                // Update image field in product
                $product->image = $fileNameToStore;
            }

            // Update other fields
            $product->name = $request->name;
            $product->description = $request->description;
            $product->price = $request->price;
            $product->category_id = $request->category_id;

            // Save the updated product
            $product->save();

            // Prepare the response with correct image URL if an image was uploaded
            $product->image_url = $product->image ? asset('storage/products/' . $product->image) : null;

            // Return success response
            return response()->json([
                'status' => true,
                'data' => new ProductResource($product),
                'message' => 'Product updated successfully'
            ]);

        } catch (\Exception $error) {
            // Return error response if an exception occurs
            return response()->json([
                'status' => false,
                'message' => 'Product update failed',
                'error' => $error->getMessage()
            ], 400);
        }
    }

    /**
     * Remove the specified product from storage.
     *
     * @param  \App\Models\Product  $product
     * @return \Illuminate\Http\Response
     */
    public function destroy(Product $product)
    {
        try {
            // Delete associated image from storage
            if ($product->image) {
                Storage::delete('public/products/' . $product->image);
            }

            // Delete the product
            $product->delete();

            // Return success response
            return response()->json([
                'status' => true,
                'message' => 'Product deleted successfully'
            ]);

        } catch (\Exception $error) {
            // Return error response if an exception occurs
            return response()->json([
                'status' => false,
                'message' => 'Product deletion failed',
                'error' => $error->getMessage()
            ], 400);
        }
    }





    /**
     * Get the image for a specific product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function getImage($id)
    {
        try {
            $product = Product::findOrFail($id);

            if ($product->image) {
                $path = public_path('/api/products/image/' . $product->image);
                return Response::file($path);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Image not found for product with ID ' . $id,
                ], 404);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Error retrieving image: ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
     * Retrieve ratings for a specific product.
     *
     * @param  int  $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductRatings($productId)
    {
        try {
            // Find the product
            $product = Product::findOrFail($productId);

            // Fetch ratings associated with this product
            $ratings = ProductUserRating::where('product_id', $productId)
                ->with('user')
                ->get();

            // Format response data
            $formattedRatings = $ratings->map(function ($rating) use ($product) {
                $role = ($rating->user_id === $product->user_id) ? 'owner' : 'customer';

                return [
                    'rating_id' => $rating->id,
                    'user_id' => $rating->user->id,
                    'user_name' => $rating->user->name,
                    'user_email' => $rating->user->email,
                    'rating' => $rating->rating,
                    'role' => $role,
                    'created_at' => $rating->created_at,
                ];
            });

            // Return JSON response with success message and ratings data
            return response()->json([
                'status' => true,
                'message' => 'Product ratings retrieved successfully',
                'data' => [
                    'product_id' => $productId,
                    'product_name' => $product->name,
                    'ratings' => $formattedRatings,
                ],
            ], 200);
        } catch (Exception $error) {
            // Return JSON response with error message and details
            return response()->json([
                'status' => false,
                'message' => 'Failed to retrieve product ratings',
                'error' => $error->getMessage(),
            ], 500);
        }
    }
}

