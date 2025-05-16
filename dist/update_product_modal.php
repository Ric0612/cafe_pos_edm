<!-- Modal for Updating Product -->
<div class="modal fade" id="updateProductModal" tabindex="-1" aria-labelledby="updateProductModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="updateProductModalLabel">Update Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form action="inventory.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" id="update_product_id" name="product_ID">
                    <div class="mb-3">
                        <label for="update_name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="update_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_category" class="form-label">Category</label>
                        <select class="form-select" id="update_category" name="category" required>
                            <option value="Cold Drinks">Cold Drinks</option>
                            <option value="Hot Drinks">Hot Drinks</option>
                            <option value="Snacks">Snacks</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="update_description" class="form-label">Description</label>
                        <textarea class="form-control" id="update_description" name="description" rows="3" required></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="update_price" class="form-label">Price</label>
                        <input type="number" class="form-control" id="update_price" name="price" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_stock" class="form-label">Stock</label>
                        <input type="number" class="form-control" id="update_stock" name="stock" required>
                    </div>
                    <div class="mb-3">
                        <label for="update_image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="update_image" name="image" accept="image/*">
                        <small class="text-muted">Leave empty to keep current image</small>
                    </div>
                    <div class="mb-3">
                        <div id="current_image_preview" class="text-center">
                            <img src="" alt="Current product image" style="max-width: 200px; display: none;" class="img-thumbnail">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary" name="update_product">Update Product</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function openUpdateModal(productId) {
    // Fetch product details using AJAX
    fetch('get_product.php?id=' + productId)
        .then(response => {
            if (!response.ok) {
                return response.json().then(err => {
                    throw new Error(err.error || 'Failed to fetch product details');
                });
            }
            return response.json();
        })
        .then(product => {
            // Populate the form fields
            document.getElementById('update_product_id').value = product.product_ID;
            document.getElementById('update_name').value = product.name;
            document.getElementById('update_category').value = product.category;
            document.getElementById('update_description').value = product.description;
            document.getElementById('update_price').value = product.price;
            document.getElementById('update_stock').value = product.stock;

            // Update image preview
            const imagePreview = document.querySelector('#current_image_preview img');
            if (product.image) {
                imagePreview.src = '../uploads/' + product.image;
                imagePreview.style.display = 'block';
            } else {
                imagePreview.src = '../img/default-product.jpg';
                imagePreview.style.display = 'block';
            }

            // Show the modal
            const updateModal = new bootstrap.Modal(document.getElementById('updateProductModal'));
            updateModal.show();
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to fetch product details',
                confirmButtonColor: '#6c4f3d'
            });
        });
}
</script> 